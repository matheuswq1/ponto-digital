<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Services\PayPeriodAcknowledgementAuditService;
use App\Services\PayPeriodClosureService;
use App\Services\PayPeriodMirrorPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayPeriodClosureController extends Controller
{
    public function __construct(
        private readonly PayPeriodClosureService $payPeriodClosureService,
        private readonly PayPeriodMirrorPayloadService $mirrorPayloadService,
        private readonly PayPeriodAcknowledgementAuditService $acknowledgementAuditService,
    ) {}

    /**
     * Lista fechos da empresa (gestor/admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->company_id) {
            return response()->json(['message' => 'Empresa não associada ao utilizador.'], 403);
        }

        $closures = PayPeriodClosure::query()
            ->where('company_id', $user->company_id)
            ->withCount([
                'acknowledgements as pending_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE),
                'acknowledgements as approved_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_APROVADO),
                'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO)->whereNull('superseded_at'),
                'acknowledgements as people_total',
            ])
            ->orderByDesc('period_end')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'data' => $closures->map(fn (PayPeriodClosure $c) => $this->closurePayload($c))->values(),
            'meta' => [
                'current_page' => $closures->currentPage(),
                'last_page' => $closures->lastPage(),
                'total' => $closures->total(),
            ],
        ]);
    }

    /**
     * Cria fecho de período e linhas «pendente» (empresa inteira, departamentos ou colaboradores).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->company_id) {
            return response()->json(['message' => 'Empresa não associada ao utilizador.'], 403);
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:5000',
            'closure_scope' => 'sometimes|in:company,departments,employees',
            'department_ids' => 'exclude_unless:closure_scope,departments|required|array|min:1',
            'department_ids.*' => 'integer',
            'employee_ids' => 'exclude_unless:closure_scope,employees|required|array|min:1',
            'employee_ids.*' => 'integer',
            'supersedes_acknowledgement_ids' => 'sometimes|array|min:1',
            'supersedes_acknowledgement_ids.*' => 'integer',
        ]);

        $supersedes = array_values(array_unique(array_map(
            'intval',
            $validated['supersedes_acknowledgement_ids'] ?? [],
        )));

        try {
            if ($supersedes !== []) {
                $closure = $this->payPeriodClosureService->closePeriod(
                    $user,
                    (int) $user->company_id,
                    $validated['period_start'],
                    $validated['period_end'],
                    $validated['notes'] ?? null,
                    [],
                    $supersedes,
                );
            } else {
                $scope = $validated['closure_scope'] ?? PayPeriodClosureService::SCOPE_COMPANY;

                $employeeIds = $this->payPeriodClosureService->resolveTargetEmployeeIds(
                    (int) $user->company_id,
                    $scope,
                    $validated['department_ids'] ?? [],
                    $validated['employee_ids'] ?? [],
                );

                $closure = $this->payPeriodClosureService->closePeriod(
                    $user,
                    (int) $user->company_id,
                    $validated['period_start'],
                    $validated['period_end'],
                    $validated['notes'] ?? null,
                    $employeeIds,
                );
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $closure->loadCount([
            'acknowledgements as pending_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE),
            'acknowledgements as approved_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_APROVADO),
            'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO)->whereNull('superseded_at'),
            'acknowledgements as people_total',
        ]);

        return response()->json([
            'message' => 'Período fechado. Os funcionários podem consultar e responder ao espelho.',
            'data' => $this->closurePayload($closure),
        ], 201);
    }

    /**
     * Remove o fecho apenas se todos os colaboradores ainda estiverem pendentes.
     */
    public function destroy(Request $request, PayPeriodClosure $payPeriodClosure): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            if ($user->company_id !== null && (int) $payPeriodClosure->company_id !== (int) $user->company_id) {
                return response()->json(['message' => 'Período não encontrado.'], 404);
            }
        } elseif (! $user->company_id || (int) $payPeriodClosure->company_id !== (int) $user->company_id) {
            return response()->json(['message' => 'Período não encontrado.'], 404);
        }

        if (! $payPeriodClosure->canDeleteWhileAllPending()) {
            return response()->json([
                'message' => 'Só é possível excluir enquanto todos os colaboradores estiverem pendentes.',
            ], 422);
        }

        $payPeriodClosure->delete();

        return response()->json(['message' => 'Fecho removido.']);
    }

    /**
     * Lista reconhecimentos do funcionário autenticado.
     */
    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $rows = EmployeePayPeriodAcknowledgement::query()
            ->where('employee_id', $employee->id)
            ->whereNull('superseded_at')
            ->with('payPeriodClosure')
            ->get()
            ->sortByDesc(fn (EmployeePayPeriodAcknowledgement $a) => $a->payPeriodClosure->period_end)
            ->values();

        return response()->json([
            'data' => $rows->map(fn (EmployeePayPeriodAcknowledgement $r) => $this->mineRowPayload($r)),
        ]);
    }

    /**
     * Detalhe do período + dias trabalhados para o funcionário autenticado.
     */
    public function mineDetail(Request $request, PayPeriodClosure $payPeriodClosure): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        if ($employee->company_id !== $payPeriodClosure->company_id) {
            return response()->json(['message' => 'Período não encontrado.'], 404);
        }

        $ack = EmployeePayPeriodAcknowledgement::query()
            ->where('pay_period_closure_id', $payPeriodClosure->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $ack) {
            return response()->json(['message' => 'Não existe espelho para si neste período.'], 404);
        }

        if ($ack->superseded_at !== null) {
            return response()->json([
                'message' => 'Este espelho foi substituído por uma correção. Consulte o período mais recente na lista.',
            ], 410);
        }

        $mirrorPayload = $this->mirrorPayloadService->buildMirrorPayload(
            $employee,
            $payPeriodClosure,
            $ack,
            $request,
        );

        return response()->json([
            'data' => $mirrorPayload,
        ]);
    }

    /**
     * Funcionário aprova ou rejeita o espelho do período fechado.
     */
    public function respond(Request $request, PayPeriodClosure $payPeriodClosure): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:5000',
            'client_meta' => 'nullable|array',
            'client_meta.app_version' => 'nullable|string|max:128',
            'client_meta.build_number' => 'nullable|string|max:64',
            'client_meta.platform' => 'nullable|string|max:64',
            'client_meta.device_id' => 'nullable|string|max:128',
            'client_meta.locale' => 'nullable|string|max:64',
        ]);

        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        if ($employee->company_id !== $payPeriodClosure->company_id) {
            return response()->json(['message' => 'Período não encontrado.'], 404);
        }

        $ack = EmployeePayPeriodAcknowledgement::query()
            ->where('pay_period_closure_id', $payPeriodClosure->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $ack) {
            return response()->json(['message' => 'Não existe espelho para si neste período.'], 404);
        }

        if ($ack->superseded_at !== null) {
            return response()->json([
                'message' => 'Este espelho foi substituído por uma correção. Consulte o período mais recente na lista.',
            ], 410);
        }

        if (! $ack->isPending()) {
            return response()->json(['message' => 'Este período já foi respondido.'], 422);
        }

        $notesRaw = $validated['notes'] ?? null;
        $notesTrimmed = $notesRaw !== null && $notesRaw !== '' ? trim((string) $notesRaw) : null;
        if ($notesTrimmed === '') {
            $notesTrimmed = null;
        }

        $clientMeta = PayPeriodAcknowledgementAuditService::normalizeClientMeta($validated['client_meta'] ?? null);

        try {
            $audit = DB::transaction(function () use ($request, $ack, $payPeriodClosure, $employee, $validated, $notesTrimmed, $clientMeta) {
                $decision = $validated['decision'];

                $record = $this->acknowledgementAuditService->recordDecision(
                    $request,
                    $ack,
                    $payPeriodClosure,
                    $employee,
                    $decision,
                    $notesTrimmed,
                    $clientMeta,
                );

                $ack->status = $decision === 'approve'
                    ? EmployeePayPeriodAcknowledgement::STATUS_APROVADO
                    : EmployeePayPeriodAcknowledgement::STATUS_REJEITADO;
                $ack->employee_notes = $notesTrimmed;
                $ack->responded_at = now();
                $ack->save();

                return $record;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Não foi possível registar a resposta de forma segura. Tente novamente ou contacte o RH.',
            ], 503);
        }

        $ack->refresh();

        return response()->json([
            'message' => $ack->status === EmployeePayPeriodAcknowledgement::STATUS_APROVADO
                ? 'Espelho aceite com sucesso.'
                : 'Espelho rejeitado. O RH será notificado pela plataforma.',
            'data' => [
                'id' => $ack->id,
                'status' => $ack->status,
                'employee_notes' => $ack->employee_notes,
                'responded_at' => $ack->responded_at?->toIso8601String(),
                'audit_snapshot_hash' => $audit['snapshot_hash'],
                'audit_event_id' => $audit['audit_event_id'],
                'terms_version' => config('pay_mirror.terms_version'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function closurePayload(PayPeriodClosure $c): array
    {
        $pending = (int) ($c->pending_count ?? 0);
        $approved = (int) ($c->approved_count ?? 0);
        $rejected = (int) ($c->rejected_count ?? 0);

        return [
            'id' => $c->id,
            'period_start' => $c->period_start->toDateString(),
            'period_end' => $c->period_end->toDateString(),
            'notes' => $c->notes,
            'is_correction' => $c->corrected_from_closure_id !== null,
            'corrected_from_closure_id' => $c->corrected_from_closure_id,
            'closed_at' => $c->closed_at->toIso8601String(),
            'pending_count' => $pending,
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'people_total' => (int) ($c->people_total ?? ($pending + $approved + $rejected)),
            'deletable_all_pending' => $pending > 0 && $approved === 0 && $rejected === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mineRowPayload(EmployeePayPeriodAcknowledgement $r): array
    {
        $c = $r->payPeriodClosure;

        return [
            'id' => $r->id,
            'status' => $r->status,
            'employee_notes' => $r->employee_notes,
            'responded_at' => $r->responded_at?->toIso8601String(),
            'closure' => [
                'id' => $c->id,
                'period_start' => $c->period_start->toDateString(),
                'period_end' => $c->period_end->toDateString(),
                'notes' => $c->notes,
                'is_correction' => $c->corrected_from_closure_id !== null,
                'closed_at' => $c->closed_at->toIso8601String(),
            ],
        ];
    }
}
