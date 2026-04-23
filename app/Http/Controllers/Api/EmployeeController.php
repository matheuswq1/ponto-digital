<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Company;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    private function gestorCompanyId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }

        return $user->employee?->company_id ?? $user->company_id;
    }

    private function assertCanAccessEmployee(Request $request, Employee $employee): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }
        $cid = $this->gestorCompanyId($request);
        if (! $cid || (int) $employee->company_id !== (int) $cid) {
            abort(403, 'Sem permissão.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Employee::with('user', 'company', 'workSchedule')
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas('user', function ($uq) use ($request) {
                    $uq->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                })->orWhere('cpf', 'like', "%{$request->search}%");
            })
            ->when(
                $request->has('active'),
                fn ($q) => $q->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN))
            );

        if (! $request->user()->isAdmin()) {
            $cid = $this->gestorCompanyId($request);
            if ($cid) {
                $query->where('company_id', $cid);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $employees = $query->orderBy('id')->paginate(20);

        return response()->json([
            'data' => EmployeeResource::collection($employees),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'cpf' => 'required|string|max:14|unique:employees,cpf',
            'cargo' => 'required|string|max:100',
            'department' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:50',
            'admission_date' => 'required|date',
            'contract_type' => 'nullable|in:clt,pj,estagio,temporario',
            'weekly_hours' => 'nullable|integer|min:1|max:60',
            'pis' => 'nullable|string|max:20',
            'company_id' => 'required|exists:companies,id',
            'password' => 'nullable|string|min:8',
            'schedule' => 'nullable|array',
            'schedule.entry_time' => 'nullable|date_format:H:i',
            'schedule.lunch_start' => 'nullable|date_format:H:i',
            'schedule.lunch_end' => 'nullable|date_format:H:i',
            'schedule.exit_time' => 'nullable|date_format:H:i',
            'schedule.tolerance_minutes' => 'nullable|integer|min:0|max:60',
        ]);

        if (! $request->user()->isAdmin()) {
            $cid = $this->gestorCompanyId($request);
            if (! $cid || (int) $request->company_id !== (int) $cid) {
                return response()->json(['message' => 'Sem permissão para criar colaboradores nesta empresa.'], 403);
            }
        }

        $company = Company::findOrFail($request->company_id);
        $employee = $this->employeeService->create($request->all(), $company);

        return response()->json([
            'message' => 'Funcionário criado com sucesso.',
            'data' => new EmployeeResource($employee),
        ], 201);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->assertCanAccessEmployee($request, $employee);

        $employee->load('user', 'company', 'workSchedule', 'workSchedules');

        return response()->json(['data' => new EmployeeResource($employee)]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $this->assertCanAccessEmployee($request, $employee);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'weekly_hours' => 'nullable|integer|min:1|max:60',
            'active' => 'nullable|boolean',
        ]);

        $employee = $this->employeeService->update($employee, $request->all());

        return response()->json([
            'message' => 'Funcionário atualizado com sucesso.',
            'data' => new EmployeeResource($employee),
        ]);
    }

    public function dismiss(Request $request, Employee $employee): JsonResponse
    {
        $this->assertCanAccessEmployee($request, $employee);

        $request->validate([
            'dismissal_date' => 'required|date',
        ]);

        $employee = $this->employeeService->dismiss($employee, $request->dismissal_date);

        return response()->json([
            'message' => 'Funcionário desligado com sucesso.',
            'data' => new EmployeeResource($employee),
        ]);
    }
}
