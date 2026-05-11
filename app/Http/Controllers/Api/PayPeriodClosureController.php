<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkDayResource;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Models\TimeRecord;
use App\Models\WorkDay;
use App\Services\PayPeriodClosureService;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayPeriodClosureController extends Controller
{
    public function __construct(
        private readonly WorkDayService $workDayService,
        private readonly PayPeriodClosureService $payPeriodClosureService,
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
                'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO),
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
        ]);

        $scope = $validated['closure_scope'] ?? PayPeriodClosureService::SCOPE_COMPANY;

        try {
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
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $closure->loadCount([
            'acknowledgements as pending_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE),
            'acknowledgements as approved_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_APROVADO),
            'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO),
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

        $start = $payPeriodClosure->period_start->toDateString();
        $end = $payPeriodClosure->period_end->toDateString();

        $balance = $this->workDayService->getPeriodBalance($employee, $start, $end);

        $workDays = $employee->workDays()
            ->whereBetween('date', [$start, $end])
            ->with('employee:id,company_id')
            ->orderBy('date')
            ->get();

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $records = $employee->timeRecords()
            ->where('datetime', '>=', Carbon::parse($start, $tz)->startOfDay())
            ->where('datetime', '<=', Carbon::parse($end, $tz)->endOfDay())
            ->orderBy('datetime')
            ->get();

        $recordsByDate = $records->groupBy(fn (TimeRecord $r) => $r->datetime->format('Y-m-d'));

        $workDaysPayload = $workDays->map(function (WorkDay $wd) use ($recordsByDate, $request) {
            $base = (new WorkDayResource($wd))->toArray($request);
            $key = $wd->date->toDateString();
            $dayRecords = $recordsByDate->get($key, collect());
            $base['time_records'] = $dayRecords->map(fn (TimeRecord $tr) => [
                'id' => $tr->id,
                'type' => $tr->type,
                'type_label' => $tr->getTypeLabel(),
                'time' => $tr->datetime->format('H:i'),
                'datetime' => $tr->datetime->format('Y-m-d\TH:i:s'),
            ])->values()->all();

            return $base;
        })->values()->all();

        return response()->json([
            'data' => [
                'acknowledgement' => [
                    'id' => $ack->id,
                    'status' => $ack->status,
                    'employee_notes' => $ack->employee_notes,
                    'responded_at' => $ack->responded_at?->toIso8601String(),
                ],
                'closure' => [
                    'id' => $payPeriodClosure->id,
                    'period_start' => $start,
                    'period_end' => $end,
                    'notes' => $payPeriodClosure->notes,
                    'closed_at' => $payPeriodClosure->closed_at->toIso8601String(),
                ],
                'summary' => [
                    'total_worked_minutes' => $balance['total_worked_minutes'],
                    'total_expected_minutes' => $balance['total_expected_minutes'],
                    'balance_minutes' => $balance['balance_minutes'],
                    'days_worked' => $balance['days_worked'],
                    'days_absent' => $balance['days_absent'],
                    'balance_hours' => $this->formatSignedHours((int) $balance['balance_minutes']),
                    'worked_hours' => $this->formatUnsignedHours((int) $balance['total_worked_minutes']),
                    'expected_hours' => $this->formatUnsignedHours((int) $balance['total_expected_minutes']),
                ],
                'work_days' => $workDaysPayload,
            ],
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

        if (! $ack->isPending()) {
            return response()->json(['message' => 'Este período já foi respondido.'], 422);
        }

        $ack->status = $validated['decision'] === 'approve'
            ? EmployeePayPeriodAcknowledgement::STATUS_APROVADO
            : EmployeePayPeriodAcknowledgement::STATUS_REJEITADO;
        $ack->employee_notes = $validated['notes'] ?? null;
        $ack->responded_at = now();
        $ack->save();

        return response()->json([
            'message' => $ack->status === EmployeePayPeriodAcknowledgement::STATUS_APROVADO
                ? 'Espelho aceite com sucesso.'
                : 'Espelho rejeitado. O RH será notificado pela plataforma.',
            'data' => [
                'id' => $ack->id,
                'status' => $ack->status,
                'employee_notes' => $ack->employee_notes,
                'responded_at' => $ack->responded_at->toIso8601String(),
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
                'closed_at' => $c->closed_at->toIso8601String(),
            ],
        ];
    }

    private function formatUnsignedHours(int $minutes): string
    {
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    private function formatSignedHours(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }
        $sign = $minutes > 0 ? '+' : '−';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $h, $m);
    }
}
