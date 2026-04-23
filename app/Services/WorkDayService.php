<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkDay;
use Carbon\Carbon;

class WorkDayService
{
    public function calculateAndSave(Employee $employee, string $date): WorkDay
    {
        $records = $employee->timeRecords()
            ->whereDate('datetime', $date)
            ->orderBy('datetime')
            ->get();

        $data = $this->calculate($employee, $records, $date);

        return WorkDay::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            $data
        );
    }

    public function calculate(Employee $employee, $records, string $date): array
    {
        $schedule = $employee->workSchedule;

        $entryRecord = $records->firstWhere('type', 'entrada');
        $lunchStartRecord = $records->firstWhere('type', 'saida_almoco');
        $lunchEndRecord = $records->firstWhere('type', 'volta_almoco');
        $exitRecord = $records->firstWhere('type', 'saida');

        $entryTime = $entryRecord?->datetime?->format('H:i:s');
        $lunchStart = $lunchStartRecord?->datetime?->format('H:i:s');
        $lunchEnd = $lunchEndRecord?->datetime?->format('H:i:s');
        $exitTime = $exitRecord?->datetime?->format('H:i:s');

        $totalMinutes = 0;
        $lunchMinutes = 0;

        if ($entryRecord && $exitRecord) {
            $totalMinutes = $exitRecord->datetime->diffInMinutes($entryRecord->datetime);

            if ($lunchStartRecord && $lunchEndRecord) {
                $lunchMinutes = $lunchEndRecord->datetime->diffInMinutes($lunchStartRecord->datetime);
                $totalMinutes -= $lunchMinutes;
            }
        }

        $expectedMinutes = $schedule?->getExpectedMinutes() ?? $employee->dailyExpectedMinutes();
        $extraMinutes = $totalMinutes - $expectedMinutes;

        return [
            'entry_time' => $entryTime,
            'lunch_start' => $lunchStart,
            'lunch_end' => $lunchEnd,
            'exit_time' => $exitTime,
            'total_minutes' => max(0, $totalMinutes),
            'expected_minutes' => $expectedMinutes,
            'extra_minutes' => $exitRecord ? $extraMinutes : 0,
            'lunch_minutes' => $lunchMinutes,
            'is_closed' => $exitRecord !== null,
        ];
    }

    public function getMonthSummary(Employee $employee, int $year, int $month): array
    {
        $workDays = $employee->workDays()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        $totalWorked = $workDays->sum('total_minutes');
        $totalExpected = $workDays->sum('expected_minutes');
        $totalExtra = $workDays->sum('extra_minutes');
        $totalAbsences = $workDays->where('status', 'falta')->count();

        return [
            'year' => $year,
            'month' => $month,
            'work_days' => $workDays,
            'summary' => [
                'total_worked_minutes' => $totalWorked,
                'total_expected_minutes' => $totalExpected,
                'total_extra_minutes' => $totalExtra,
                'total_absences' => $totalAbsences,
                'balance_hours' => $this->formatMinutes($totalExtra),
                'worked_hours' => $this->formatMinutes($totalWorked),
                'expected_hours' => $this->formatMinutes($totalExpected),
            ],
        ];
    }

    public function getPeriodBalance(Employee $employee, string $startDate, string $endDate): array
    {
        $workDays = $employee->workDays()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_worked_minutes' => $workDays->sum('total_minutes'),
            'total_expected_minutes' => $workDays->sum('expected_minutes'),
            'balance_minutes' => $workDays->sum('extra_minutes'),
            'days_worked' => $workDays->where('total_minutes', '>', 0)->count(),
            'days_absent' => $workDays->where('status', 'falta')->count(),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $mins = $abs % 60;
        return sprintf('%s%02d:%02d', $sign, $hours, $mins);
    }
}
