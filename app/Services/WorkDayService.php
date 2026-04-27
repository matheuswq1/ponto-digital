<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HourBankTransaction;
use App\Models\TimeRecord;
use App\Models\TimeRecordEdit;
use App\Models\WorkDay;
use Carbon\Carbon;

class WorkDayService
{
    public function calculateAndSave(Employee $employee, string $date): WorkDay
    {
        // Garantir que departamento e escala individual estão carregados
        $employee->loadMissing(['workSchedule', 'dept']);

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
        } else {
            $this->removeWorkDayOnlyHourBankRow($workDay);
        }

        return $workDay;
    }

    /**
     * Após aprovar correção de ponto, recalcula o(s) dia(s) afetados para atualizar
     * WorkDay e transações de banco de horas (antes só era disparado no registro de saída).
     */
    public function recalculateDaysForApprovedEdit(TimeRecordEdit $edit): void
    {
        $timeRecord = $edit->timeRecord;
        if (! $timeRecord) {
            return;
        }
        $employee = $timeRecord->employee;
        if (! $employee) {
            return;
        }
        $tz = config('app.timezone', 'America/Sao_Paulo');
        $dates = collect([
            $edit->original_datetime?->copy()->setTimezone($tz)->toDateString(),
            $edit->new_datetime?->copy()->setTimezone($tz)->toDateString(),
        ])->filter()->unique()->values();
        foreach ($dates as $date) {
            $this->calculateAndSave($employee, $date);
        }
    }

    /**
     * Cria ou atualiza a transação do banco de horas correspondente ao dia.
     * Faltas (extra_minutes = 0, status = falta) não geram transação.
     */
    private function syncHourBankTransaction(Employee $employee, WorkDay $workDay): void
    {
        $extra = $workDay->extra_minutes;

        // Dia sem desvio: remove linha automática vinculada a este WorkDay (ex.: ponto corrigido)
        if ($extra === 0) {
            $this->removeWorkDayOnlyHourBankRow($workDay);

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

    /**
     * Remove credit/débito automático deste WorkDay (não mexe em folga ou ajuste manual).
     */
    private function removeWorkDayOnlyHourBankRow(WorkDay $workDay): void
    {
        HourBankTransaction::query()
            ->where('work_day_id', $workDay->id)
            ->whereIn('type', ['extra', 'deficit'])
            ->delete();
    }

    public function calculate(Employee $employee, $records, string $date): array
    {
        $schedule = $employee->workSchedule;
        $dept     = $employee->dept;
        $deptRef  = ($dept && $dept->entry_time && $dept->exit_time) ? $dept : null;

        // Dia da semana (0=Dom … 6=Sáb) para expected e tolerância por dia
        $dayOfWeek = (int) Carbon::parse($date)->format('w');

        // Separar entradas e saídas em ordem
        $firstEntry = $records->firstWhere('type', 'entrada');
        $lastExit   = $records->filter(fn ($r) => $r->type === 'saida')->last();

        $entryTime = $firstEntry?->datetime?->format('H:i:s');
        $exitTime  = $lastExit?->datetime?->format('H:i:s');

        // Calcula tempo trabalhado somando pares entrada→saída consecutivos.
        // O intervalo de almoço é naturalmente deduzido: saída para almoço + entrada
        // ao retornar — esse tempo não entra no total trabalhado.
        $totalMinutes   = 0;
        $totalIntervals = 0;
        $openEntryAt    = null;
        $firstExit      = null;

        foreach ($records as $record) {
            if ($record->type === 'entrada') {
                if ($firstExit !== null) {
                    $totalIntervals += $record->datetime->diffInMinutes($firstExit);
                    $firstExit = null;
                }
                $openEntryAt = $record->datetime;
            } elseif ($record->type === 'saida' && $openEntryAt !== null) {
                $totalMinutes += $record->datetime->diffInMinutes($openEntryAt);
                $firstExit    = $record->datetime;
                $openEntryAt  = null;
            }
        }

        // Minutos esperados — prioriza departamento (por dia), depois escala individual
        $expectedMinutes = $deptRef
            ? $deptRef->getExpectedMinutesForDay($dayOfWeek)
            : ($schedule?->getExpectedMinutes() ?? $employee->dailyExpectedMinutes());

        // Tolerância — prioriza departamento, depois escala, depois 5 min padrão
        $tolerance = (int) ($deptRef?->tolerance_minutes ?? $schedule?->tolerance_minutes ?? 5);

        // Verificar se é feriado (feriados = tudo trabalhado é extra)
        $isHoliday  = Holiday::isHoliday($date, $employee->company_id);
        $isSunday   = ($dayOfWeek === 0);
        $isSaturday = ($dayOfWeek === 6);

        // Diferença bruta entre trabalhado e esperado
        $diff = $totalMinutes - $expectedMinutes;

        // Só conta como extra/falta se ultrapassar a tolerância
        $extraMinutes = 0;
        if ($lastExit !== null) {
            if ($isHoliday || $isSunday) {
                // Feriado / domingo: todo o tempo trabalhado é extra 100%
                $extraMinutes = $totalMinutes;
            } elseif ($diff > $tolerance) {
                $extraMinutes = $diff; // extra → banco de horas (crédito)
            } elseif ($diff < -$tolerance) {
                $extraMinutes = $diff; // falta → banco de horas (débito)
            }
            // Dentro da tolerância em dia útil: extraMinutes = 0
        }

        return [
            'entry_time'       => $entryTime,
            'lunch_start'      => null,
            'lunch_end'        => null,
            'exit_time'        => $exitTime,
            'total_minutes'    => max(0, $totalMinutes),
            'expected_minutes' => $expectedMinutes,
            'extra_minutes'    => $extraMinutes,
            'lunch_minutes'    => $totalIntervals,
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
