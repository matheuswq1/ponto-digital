<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\HourBankTransaction;
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

        $workDay = WorkDay::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            $data
        );

        if ($workDay->is_closed) {
            $this->syncHourBankTransaction($employee, $workDay);
        }

        return $workDay;
    }

    /**
     * Cria ou atualiza a transação do banco de horas correspondente ao dia.
     * Faltas (extra_minutes = 0, status = falta) não geram transação.
     */
    private function syncHourBankTransaction(Employee $employee, WorkDay $workDay): void
    {
        $extra = $workDay->extra_minutes;

        // Dia sem desvio ou falta sem registro não movimenta o banco
        if ($extra === 0) {
            return;
        }

        $type = $extra > 0 ? 'extra' : 'deficit';
        $description = $extra > 0
            ? 'Hora extra em ' . $workDay->date->format('d/m/Y')
            : 'Saída antecipada em ' . $workDay->date->format('d/m/Y');

        HourBankTransaction::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_day_id' => $workDay->id,
            ],
            [
                'type'           => $type,
                'minutes'        => $extra,
                'description'    => $description,
                'reference_date' => $workDay->date,
            ]
        );
    }

    public function calculate(Employee $employee, $records, string $date): array
    {
        $schedule = $employee->workSchedule;

        // Separa por tipo para compatibilidade com campos legados
        $firstEntry = $records->firstWhere('type', 'entrada');
        $lastExit = $records->filter(fn ($r) => $r->type === 'saida')->last();

        $entryTime = $firstEntry?->datetime?->format('H:i:s');
        $exitTime  = $lastExit?->datetime?->format('H:i:s');

        // Calcula tempo trabalhado somando pares entrada→saída consecutivos
        // O intervalo de almoço é naturalmente deduzido: quando o colaborador bate
        // "saída" para almoço e "entrada" ao retornar, esse tempo não entra no total.
        $totalMinutes    = 0;
        $totalIntervals  = 0; // minutos fora do trabalho (almoço, pausas)
        $openEntryAt     = null;
        $firstExit       = null;
        $lastEntryAfterExit = null;

        foreach ($records as $record) {
            if ($record->type === 'entrada') {
                if ($firstExit !== null) {
                    // Retorno de intervalo: acumula o tempo de pausa
                    $totalIntervals += $record->datetime->diffInMinutes($firstExit);
                    $firstExit = null;
                }
                $openEntryAt = $record->datetime;
                $lastEntryAfterExit = $record->datetime;
            } elseif ($record->type === 'saida' && $openEntryAt !== null) {
                $totalMinutes += $record->datetime->diffInMinutes($openEntryAt);
                $firstExit   = $record->datetime;
                $openEntryAt = null;
            }
        }

        $expectedMinutes = $schedule?->getExpectedMinutes() ?? $employee->dailyExpectedMinutes();
        $extraMinutes    = $totalMinutes - $expectedMinutes;

        return [
            'entry_time'       => $entryTime,
            'lunch_start'      => null,
            'lunch_end'        => null,
            'exit_time'        => $exitTime,
            'total_minutes'    => max(0, $totalMinutes),
            'expected_minutes' => $expectedMinutes,
            'extra_minutes'    => $lastExit ? $extraMinutes : 0,
            'lunch_minutes'    => $totalIntervals, // total de minutos de pausa registrados
            'is_closed'        => $lastExit !== null,
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
