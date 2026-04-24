<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HourBankRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HourBankController extends Controller
{
    /**
     * GET /api/v1/hour-bank/balance
     * Saldo atual do colaborador + totais por tipo.
     */
    public function balance(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Colaborador não encontrado.'], 404);
        }

        $transactions = $employee->hourBankTransactions()
            ->orderByDesc('reference_date')
            ->get();

        $totalMinutes    = (int) $transactions->sum('minutes');
        $creditMinutes   = (int) $transactions->where('minutes', '>', 0)->sum('minutes');
        $debitMinutes    = (int) $transactions->where('minutes', '<', 0)->sum('minutes');
        $pendingRequests = $employee->hourBankRequests()->where('status', 'pendente')->count();

        return response()->json([
            'balance' => [
                'total_minutes'   => $totalMinutes,
                'credit_minutes'  => $creditMinutes,
                'debit_minutes'   => $debitMinutes,
                'formatted'       => $employee->hour_bank_balance_formatted,
                'is_positive'     => $totalMinutes >= 0,
                'pending_requests' => $pendingRequests,
            ],
        ]);
    }

    /**
     * GET /api/v1/hour-bank/transactions
     * Histórico de movimentações (últimos 90 dias ou filtro por mês).
     */
    public function transactions(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Colaborador não encontrado.'], 404);
        }

        $query = $employee->hourBankTransactions()
            ->orderByDesc('reference_date');

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereYear('reference_date', $request->year)
                  ->whereMonth('reference_date', $request->month);
        } else {
            $query->limit(50);
        }

        $transactions = $query->get()->map(fn ($t) => [
            'id'             => $t->id,
            'type'           => $t->type,
            'type_label'     => $t->getTypeLabel(),
            'minutes'        => $t->minutes,
            'formatted'      => $this->formatMinutes($t->minutes),
            'is_credit'      => $t->isCredit(),
            'description'    => $t->description,
            'reference_date' => $t->reference_date->format('Y-m-d'),
            'date_formatted' => $t->reference_date->format('d/m/Y'),
        ]);

        return response()->json(['data' => $transactions]);
    }

    /**
     * GET /api/v1/hour-bank/requests
     * Solicitações de folga do colaborador.
     */
    public function requests(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Colaborador não encontrado.'], 404);
        }

        $requests = $employee->hourBankRequests()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'requested_date'   => $r->requested_date->format('Y-m-d'),
                'date_formatted'   => $r->requested_date->format('d/m/Y'),
                'minutes_requested' => $r->minutes_requested,
                'hours_requested'  => $r->requested_hours,
                'justification'    => $r->justification,
                'status'           => $r->status,
                'status_label'     => $r->status_label,
                'approval_notes'   => $r->approval_notes,
                'approved_at'      => $r->approved_at?->format('d/m/Y H:i'),
                'created_at'       => $r->created_at->format('d/m/Y H:i'),
            ]);

        return response()->json(['data' => $requests]);
    }

    /**
     * POST /api/v1/hour-bank/requests
     * Cria nova solicitação de folga.
     */
    public function storeRequest(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Colaborador não encontrado.'], 404);
        }

        $data = $request->validate([
            'requested_date'    => 'required|date|after_or_equal:today',
            'minutes_requested' => 'required|integer|min:30|max:480',
            'justification'     => 'required|string|min:10|max:500',
        ], [
            'requested_date.after_or_equal' => 'A data não pode ser no passado.',
            'minutes_requested.min'         => 'Mínimo 30 minutos.',
            'minutes_requested.max'         => 'Máximo 8 horas (480 minutos) por solicitação.',
            'justification.min'             => 'Justificativa deve ter ao menos 10 caracteres.',
        ]);

        // Verifica saldo suficiente
        $balance = $employee->hour_bank_balance_minutes;
        if ($data['minutes_requested'] > $balance) {
            return response()->json([
                'message' => 'Saldo insuficiente. Seu saldo atual é ' . $employee->hour_bank_balance_formatted,
                'errors'  => ['minutes_requested' => ['Saldo insuficiente.']],
            ], 422);
        }

        // Evita solicitação duplicada para a mesma data
        $exists = $employee->hourBankRequests()
            ->where('requested_date', $data['requested_date'])
            ->whereIn('status', ['pendente', 'aprovado'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Já existe uma solicitação para esta data.',
                'errors'  => ['requested_date' => ['Data já solicitada.']],
            ], 422);
        }

        $hourBankRequest = $employee->hourBankRequests()->create($data);

        return response()->json([
            'message' => 'Solicitação enviada com sucesso. Aguarde a aprovação.',
            'data'    => [
                'id'               => $hourBankRequest->id,
                'requested_date'   => $hourBankRequest->requested_date->format('Y-m-d'),
                'date_formatted'   => $hourBankRequest->requested_date->format('d/m/Y'),
                'hours_requested'  => $hourBankRequest->requested_hours,
                'status'           => $hourBankRequest->status,
                'status_label'     => $hourBankRequest->status_label,
            ],
        ], 201);
    }

    private function formatMinutes(int $minutes): string
    {
        $sign = $minutes >= 0 ? '+' : '-';
        $abs  = abs($minutes);
        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60);
    }
}
