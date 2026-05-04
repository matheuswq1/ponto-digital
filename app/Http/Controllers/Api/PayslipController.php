<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $query = $employee->payslips()
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month');

        if ($request->filled('year')) {
            $query->where('reference_year', (int) $request->query('year'));
        }

        $payslips = $query->paginate(24);

        return response()->json([
            'data' => PayslipResource::collection($payslips),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page'    => $payslips->lastPage(),
                'total'        => $payslips->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $payslip = $employee->payslips()->findOrFail($id);

        return response()->json(['data' => new PayslipResource($payslip)]);
    }
}
