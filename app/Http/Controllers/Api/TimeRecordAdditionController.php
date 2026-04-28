<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeRecordAddition;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeRecordAdditionController extends Controller
{
    public function __construct(private readonly PushNotificationService $push) {}

    /**
     * Colaborador solicita adição de um ponto que esqueceu de bater.
     * POST /api/v1/time-records/request-addition
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'          => 'required|in:entrada,saida',
            'datetime'      => 'required|date|before:now',
            'justification' => 'required|string|min:20|max:500',
        ], [
            'datetime.before' => 'A data/hora do ponto não pode ser no futuro.',
        ]);

        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Colaborador não encontrado.'], 404);
        }

        // Impedir duplicado pendente no mesmo horário aproximado (±30 min)
        // datetime vem do app como hora local — guardar sem conversão
        $dt = Carbon::parse($request->datetime);
        $hasDuplicate = TimeRecordAddition::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'pendente')
            ->whereBetween('datetime', [$dt->copy()->subMinutes(30), $dt->copy()->addMinutes(30)])
            ->exists();

        if ($hasDuplicate) {
            return response()->json([
                'message' => 'Já existe uma solicitação pendente próxima a este horário.',
            ], 422);
        }

        $addition = TimeRecordAddition::create([
            'employee_id'   => $employee->id,
            'requested_by'  => $request->user()->id,
            'type'          => $request->type,
            'datetime'      => $dt,
            'justification' => $request->justification,
            'status'        => 'pendente',
        ]);

        return response()->json([
            'message' => 'Solicitação de adição de ponto enviada. Aguardando aprovação.',
            'data'    => $this->formatAddition($addition),
        ], 201);
    }

    /**
     * Lista as solicitações do colaborador logado.
     * GET /api/v1/time-records/additions
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $additions = TimeRecordAddition::where('employee_id', $employee->id)
            ->orderByDesc('datetime')
            ->paginate(20);

        return response()->json([
            'data' => $additions->map(fn ($a) => $this->formatAddition($a)),
            'meta' => [
                'current_page' => $additions->currentPage(),
                'last_page'    => $additions->lastPage(),
                'total'        => $additions->total(),
            ],
        ]);
    }

    private function formatAddition(TimeRecordAddition $a): array
    {
        return [
            'id'             => $a->id,
            'type'           => $a->type,
            'datetime'       => $a->datetime?->toIso8601String(),
            'datetime_local' => $a->datetime_local?->format('d/m/Y H:i'),
            'justification'  => $a->justification,
            'status'         => $a->status,
            'approval_notes' => $a->approval_notes,
            'approved_at'    => $a->approved_at?->toIso8601String(),
        ];
    }
}
