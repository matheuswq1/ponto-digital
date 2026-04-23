<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkDayResource;
use App\Models\Employee;
use App\Services\WorkDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkDayController extends Controller
{
    public function __construct(private readonly WorkDayService $workDayService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $employeeId = $request->employee_id ?? $request->user()->employee?->id;
        $employee = Employee::findOrFail($employeeId);

        if ($request->year && $request->month) {
            $result = $this->workDayService->getMonthSummary(
                $employee,
                $request->year,
                $request->month
            );

            return response()->json([
                'data' => WorkDayResource::collection($result['work_days']),
                'summary' => $result['summary'],
            ]);
        }

        $workDays = $employee->workDays()
            ->when($request->start_date, fn($q) => $q->where('date', '>=', $request->start_date))
            ->when($request->end_date, fn($q) => $q->where('date', '<=', $request->end_date))
            ->orderByDesc('date')
            ->paginate(31);

        return response()->json([
            'data' => WorkDayResource::collection($workDays),
            'meta' => [
                'current_page' => $workDays->currentPage(),
                'last_page' => $workDays->lastPage(),
                'total' => $workDays->total(),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employeeId = $request->employee_id ?? $request->user()->employee?->id;
        $employee = Employee::findOrFail($employeeId);

        $balance = $this->workDayService->getPeriodBalance(
            $employee,
            $request->start_date,
            $request->end_date
        );

        return response()->json(['data' => $balance]);
    }

    public function recalculate(Request $request, Employee $employee, string $date): JsonResponse
    {
        $workDay = $this->workDayService->calculateAndSave($employee, $date);

        return response()->json([
            'message' => 'Dia recalculado com sucesso.',
            'data' => new WorkDayResource($workDay),
        ]);
    }
}
