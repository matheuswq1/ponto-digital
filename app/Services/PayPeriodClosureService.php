<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayPeriodClosureService
{
    /**
     * @throws ValidationException
     */
    public function closePeriod(User $closedByUser, int $companyId, string $periodStart, string $periodEnd, ?string $notes = null): PayPeriodClosure
    {
        $start = Carbon::parse($periodStart)->toDateString();
        $end = Carbon::parse($periodEnd)->toDateString();

        if ($start > $end) {
            throw ValidationException::withMessages([
                'period_end' => ['A data final deve ser igual ou posterior à data inicial.'],
            ]);
        }

        $overlap = PayPeriodClosure::query()
            ->where('company_id', $companyId)
            ->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'period_start' => ['Já existe um fecho que intersecta este intervalo de datas.'],
            ]);
        }

        return DB::transaction(function () use ($closedByUser, $companyId, $start, $end, $notes) {
            $closure = PayPeriodClosure::query()->create([
                'company_id' => $companyId,
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $notes,
                'closed_at' => now(),
                'closed_by' => $closedByUser->id,
            ]);

            $employeeIds = Employee::query()
                ->where('company_id', $companyId)
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
    }
}
