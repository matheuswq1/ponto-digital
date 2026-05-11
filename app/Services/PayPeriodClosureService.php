<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayPeriodClosureService
{
    public const SCOPE_COMPANY = 'company';

    public const SCOPE_DEPARTMENTS = 'departments';

    public const SCOPE_EMPLOYEES = 'employees';

    /**
     * @param  array<int>  $departmentIds  Ids de departamentos (escopo departments).
     * @param  array<int>  $employeeIdsFilter  Ids de colaboradores (escopo employees).
     * @return array<int>
     *
     * @throws ValidationException
     */
    public function resolveTargetEmployeeIds(int $companyId, string $scope, array $departmentIds = [], array $employeeIdsFilter = []): array
    {
        $scope = strtolower($scope);

        if ($scope === self::SCOPE_COMPANY) {
            return Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        if ($scope === self::SCOPE_DEPARTMENTS) {
            $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));

            if ($departmentIds === []) {
                throw ValidationException::withMessages([
                    'department_ids' => ['Seleccione pelo menos um departamento.'],
                ]);
            }

            $valid = Department::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereIn('id', $departmentIds)
                ->pluck('id')
                ->all();

            if (count($valid) !== count($departmentIds)) {
                throw ValidationException::withMessages([
                    'department_ids' => ['Departamento inválido ou inactivo para esta empresa.'],
                ]);
            }

            $ids = Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereIn('department_id', $departmentIds)
                ->orderBy('id')
                ->pluck('id')
                ->unique()
                ->values()
                ->all();

            if ($ids === []) {
                throw ValidationException::withMessages([
                    'department_ids' => ['Nenhum colaborador activo nos departamentos seleccionados.'],
                ]);
            }

            return $ids;
        }

        if ($scope === self::SCOPE_EMPLOYEES) {
            $employeeIdsFilter = array_values(array_unique(array_map('intval', $employeeIdsFilter)));

            if ($employeeIdsFilter === []) {
                throw ValidationException::withMessages([
                    'employee_ids' => ['Seleccione pelo menos um colaborador.'],
                ]);
            }

            $ids = Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereIn('id', $employeeIdsFilter)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (count($ids) !== count($employeeIdsFilter)) {
                throw ValidationException::withMessages([
                    'employee_ids' => ['Um ou mais colaboradores são inválidos ou não pertencem à empresa.'],
                ]);
            }

            return $ids;
        }

        throw ValidationException::withMessages([
            'closure_scope' => ['Escopo de fecho inválido.'],
        ]);
    }

    /**
     * @param  array<int>  $employeeIds
     *
     * @throws ValidationException
     */
    public function closePeriod(User $closedByUser, int $companyId, string $periodStart, string $periodEnd, ?string $notes, array $employeeIds): PayPeriodClosure
    {
        $start = Carbon::parse($periodStart)->toDateString();
        $end = Carbon::parse($periodEnd)->toDateString();

        if ($start > $end) {
            throw ValidationException::withMessages([
                'period_end' => ['A data final deve ser igual ou posterior à data inicial.'],
            ]);
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'closure_scope' => ['Não há colaboradores para incluir neste fecho.'],
            ]);
        }

        $this->assertNoOverlappingPeriodForEmployees($companyId, $start, $end, $employeeIds);

        return DB::transaction(function () use ($closedByUser, $companyId, $start, $end, $notes, $employeeIds) {
            $closure = PayPeriodClosure::query()->create([
                'company_id' => $companyId,
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $notes,
                'closed_at' => now(),
                'closed_by' => $closedByUser->id,
            ]);

            foreach ($employeeIds as $employeeId) {
                EmployeePayPeriodAcknowledgement::query()->create([
                    'pay_period_closure_id' => $closure->id,
                    'employee_id' => $employeeId,
                    'status' => EmployeePayPeriodAcknowledgement::STATUS_PENDENTE,
                ]);
            }

            return $closure->fresh(['company']);
        });
    }

    /**
     * @param  array<int>  $employeeIds
     */
    private function assertNoOverlappingPeriodForEmployees(int $companyId, string $start, string $end, array $employeeIds): void
    {
        $exists = EmployeePayPeriodAcknowledgement::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('payPeriodClosure', function ($q) use ($companyId, $start, $end) {
                $q->where('company_id', $companyId)
                    ->where('period_start', '<=', $end)
                    ->where('period_end', '>=', $start);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_start' => ['Um ou mais colaboradores seleccionados já têm um fecho que intersecta este período.'],
            ]);
        }
    }
}
