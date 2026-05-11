<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkDayResource;
use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayPeriodClosureController extends Controller
{
    public function __construct(private readonly WorkDayService $workDayService) {}

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
     * Cria fecho de período e linhas «pendente» para todos os funcionários activos.
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
        ]);

        $start = Carbon::parse($validated['period_start'])->toDateString();
        $end = Carbon::parse($validated['period_end'])->toDateString();

        $overlap = PayPeriodClosure::query()
            ->where('company_id', $user->company_id)
            ->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Já existe um fecho que intersecta este intervalo de datas.',
            ], 422);
        }

        $closure = DB::transaction(function () use ($user, $start, $end, $validated) {
            /** @var PayPeriodClosure $closure */
            $closure = PayPeriodClosure::query()->create([
                'company_id' => $user->company_id,
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $validated['notes'] ?? null,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            $employeeIds = Employee::query()
                ->where('company_id', $user->company_id)
                ->where('active', true)
                ->pluck('id');

            foreach ($employeeIds as $employeeId) {
                EmployeePayPeriodAcknowledgement::query()->create([
                    'pay_period_closure_id' => $closure->id,
                    'employee_id' => $employeeId,
                    'status' => EmployeePayPeriodAcknowledgement::STATUS_PENDENTE,
                ]);
            }

            return $closure->fresh(['company']);
        });

        $closure->loadCount([
            'acknowledgements as pending_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE),
            'acknowledgements as approved_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_APROVADO),
            'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO),
        ]);

        return response()->json([
            'message' => 'Período fechado. Os funcionários podem consultar e responder ao espelho.',
            'data' => $this->closurePayload($closure),
        ], 201);
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
            ->orderBy('date')
            ->get();

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
                'work_days' => WorkDayResource::collection($workDays),
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
        return [
            'id' => $c->id,
            'period_start' => $c->period_start->toDateString(),
            'period_end' => $c->period_end->toDateString(),
            'notes' => $c->notes,
            'closed_at' => $c->closed_at->toIso8601String(),
            'pending_count' => (int) ($c->pending_count ?? 0),
            'approved_count' => (int) ($c->approved_count ?? 0),
            'rejected_count' => (int) ($c->rejected_count ?? 0),
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
