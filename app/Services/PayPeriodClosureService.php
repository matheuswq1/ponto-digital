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
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

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
     * @param  array<int>  $employeeIds  Ignorado quando $supersedesAcknowledgementIds é não vazio (lista deriva dos reconhecimentos).
     * @param  array<int>|null  $supersedesAcknowledgementIds  IDs em `pay_period_acknowledgements` com estado rejeitado a substituir.
     *
     * @throws ValidationException
     */
    public function closePeriod(
        User $closedByUser,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        ?string $notes,
        array $employeeIds,
        ?array $supersedesAcknowledgementIds = null,
    ): PayPeriodClosure {
        $start = Carbon::parse($periodStart)->toDateString();
        $end = Carbon::parse($periodEnd)->toDateString();

        if ($start > $end) {
            throw ValidationException::withMessages([
                'period_end' => ['A data final deve ser igual ou posterior à data inicial.'],
            ]);
        }

        $supersedesIds = [];
        $correctedFromClosureId = null;

        if ($supersedesAcknowledgementIds !== null && $supersedesAcknowledgementIds !== []) {
            $supersedesIds = array_values(array_unique(array_map('intval', $supersedesAcknowledgementIds)));
            [$employeeIds, $correctedFromClosureId] = $this->validateCorrectionAcknowledgements(
                $companyId,
                $start,
                $end,
                $supersedesIds,
            );
        } else {
            $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

            if ($employeeIds === []) {
                throw ValidationException::withMessages([
                    'closure_scope' => ['Não há colaboradores para incluir neste fecho.'],
                ]);
            }
        }

        $this->assertNoOverlappingPeriodForEmployees($companyId, $start, $end, $employeeIds, $supersedesIds);

        $closure = DB::transaction(function () use ($closedByUser, $companyId, $start, $end, $notes, $employeeIds, $correctedFromClosureId, $supersedesIds) {
            $closure = PayPeriodClosure::query()->create([
                'company_id' => $companyId,
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $notes,
                'closed_at' => now(),
                'closed_by' => $closedByUser->id,
                'corrected_from_closure_id' => $correctedFromClosureId,
            ]);

            foreach ($employeeIds as $employeeId) {
                EmployeePayPeriodAcknowledgement::query()->create([
                    'pay_period_closure_id' => $closure->id,
                    'employee_id' => $employeeId,
                    'status' => EmployeePayPeriodAcknowledgement::STATUS_PENDENTE,
                ]);
            }

            if ($supersedesIds !== []) {
                EmployeePayPeriodAcknowledgement::query()
                    ->whereIn('id', $supersedesIds)
                    ->update(['superseded_at' => now()]);
            }

            return $closure->fresh(['company']);
        });

        $this->pushNotificationService->notifyPayPeriodClosure($closure, $employeeIds);

        return $closure;
    }

    /**
     * @param  array<int>  $supersedesIds
     * @return array{0: array<int>, 1: int}
     */
    private function validateCorrectionAcknowledgements(
        int $companyId,
        string $start,
        string $end,
        array $supersedesIds,
    ): array {
        if ($supersedesIds === []) {
            throw ValidationException::withMessages([
                'supersedes_acknowledgement_ids' => ['Indique pelo menos um reconhecimento contestado a substituir.'],
            ]);
        }

        $acks = EmployeePayPeriodAcknowledgement::query()
            ->whereIn('id', $supersedesIds)
            ->with('payPeriodClosure')
            ->get();

        $uniqueRequested = array_values(array_unique($supersedesIds));

        if ($acks->count() !== count($uniqueRequested)) {
            throw ValidationException::withMessages([
                'supersedes_acknowledgement_ids' => ['Um ou mais reconhecimentos são inválidos.'],
            ]);
        }

        $closureIds = $acks->pluck('pay_period_closure_id')->unique()->values();
        if ($closureIds->count() !== 1) {
            throw ValidationException::withMessages([
                'supersedes_acknowledgement_ids' => ['Os reconhecimentos devem pertencer ao mesmo fecho original.'],
            ]);
        }

        $origClosure = $acks->first()->payPeriodClosure;
        if ((int) $origClosure->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'supersedes_acknowledgement_ids' => ['Fecho não pertence a esta empresa.'],
            ]);
        }

        if ($origClosure->period_start->toDateString() !== $start || $origClosure->period_end->toDateString() !== $end) {
            throw ValidationException::withMessages([
                'period_start' => ['Para correção, o período deve ser exactamente o do espelho contestado.'],
            ]);
        }

        foreach ($acks as $ack) {
            if ($ack->status !== EmployeePayPeriodAcknowledgement::STATUS_REJEITADO) {
                throw ValidationException::withMessages([
                    'supersedes_acknowledgement_ids' => ['Só podem ser substituídos reconhecimentos contestados (rejeitados).'],
                ]);
            }
            if ($ack->superseded_at !== null) {
                throw ValidationException::withMessages([
                    'supersedes_acknowledgement_ids' => ['Este reconhecimento já foi substituído por uma correção.'],
                ]);
            }
        }

        $employeeIds = $acks->pluck('employee_id')->unique()->sort()->values()->all();

        return [$employeeIds, (int) $origClosure->id];
    }

    /**
     * @param  array<int>  $employeeIds
     * @param  array<int>  $allowedSupersededAcknowledgementIds  Conflitos nestes IDs são autorizados (correção).
     */
    private function assertNoOverlappingPeriodForEmployees(
        int $companyId,
        string $start,
        string $end,
        array $employeeIds,
        array $allowedSupersededAcknowledgementIds = [],
    ): void {
        $conflictIds = EmployeePayPeriodAcknowledgement::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('superseded_at')
            ->whereHas('payPeriodClosure', function ($q) use ($companyId, $start, $end) {
                $q->where('company_id', $companyId)
                    ->where('period_start', '<=', $end)
                    ->where('period_end', '>=', $start);
            })
            ->pluck('id')
            ->all();

        if ($conflictIds === []) {
            return;
        }

        $allowedSet = array_fill_keys(array_map('intval', $allowedSupersededAcknowledgementIds), true);

        foreach ($conflictIds as $cid) {
            if (! isset($allowedSet[(int) $cid])) {
                throw ValidationException::withMessages([
                    'period_start' => ['Um ou mais colaboradores já têm um fecho activo neste período. Utilize a opção de correção sobre os reconhecimentos contestados.'],
                ]);
            }
        }
    }
}
