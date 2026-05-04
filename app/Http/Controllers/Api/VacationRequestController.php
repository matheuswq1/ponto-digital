<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VacationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['data' => []]);
        }

        $requests = VacationRequest::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->toArray($r));

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $validated = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:500',
        ]);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end   = \Carbon\Carbon::parse($validated['end_date']);
        $days  = $start->diffInWeekdays($end) + 1;

        // Verifica sobreposição
        $overlap = VacationRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pendente', 'aprovado'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhereBetween('end_date',   [$validated['start_date'], $validated['end_date']]);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Já existe uma solicitação aprovada ou pendente neste período.',
            ], 422);
        }

        $vacReq = VacationRequest::create([
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'days'        => $days,
            'reason'      => $validated['reason'] ?? null,
        ]);

        return response()->json(['data' => $this->toArray($vacReq)], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $req = VacationRequest::where('employee_id', $employee->id)
            ->where('status', 'pendente')
            ->findOrFail($id);

        $req->delete();

        return response()->json(['message' => 'Solicitação cancelada.']);
    }

    private function toArray(VacationRequest $r): array
    {
        return [
            'id'           => $r->id,
            'start_date'   => $r->start_date->toDateString(),
            'end_date'     => $r->end_date->toDateString(),
            'days'         => $r->days,
            'reason'       => $r->reason,
            'status'       => $r->status,
            'status_label' => $r->getStatusLabel(),
            'review_notes' => $r->review_notes,
            'reviewed_at'  => $r->reviewed_at?->toISOString(),
            'created_at'   => $r->created_at->toISOString(),
        ];
    }
}
