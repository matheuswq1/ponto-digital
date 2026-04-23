<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Employee;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class RecalculateMonthlyBalance implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly Employee $employee,
        private readonly int $year,
        private readonly int $month,
    ) {}

    public function handle(WorkDayService $workDayService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $startDate = Carbon::create($this->year, $this->month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $today = Carbon::today();

        $current = $startDate->copy();
        while ($current->lte($endDate) && $current->lte($today)) {
            $date = $current->toDateString();

            $hasRecords = $this->employee->timeRecords()
                ->whereDate('datetime', $date)
                ->exists();

            if ($hasRecords) {
                $workDayService->calculateAndSave($this->employee, $date);
            }

            $current->addDay();
        }
    }

    public static function dispatchForCompany(Company $company, int $year, int $month): void
    {
        $jobs = $company->activeEmployees()
            ->get()
            ->map(fn($employee) => new self($employee, $year, $month));

        Bus::batch($jobs)
            ->name("Recalculo mensal {$company->name} {$year}/{$month}")
            ->dispatch();
    }
}
