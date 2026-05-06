<?php

namespace App\Services;

use App\DTO\WorkToleranceContext;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HourBankTransaction;
use App\Models\TimeRecordEdit;
use App\Models\WorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WorkDayService
{
    public function __construct(
        private readonly WorkToleranceResolver $toleranceResolver,
    ) {}

    /**
     * @param  bool  $preserveClosedToleranceSnapshot  Se true e o dia já estava fechado com snapshot válido,
     *                                                 mantém o JSON de auditoria (evita drift em replays); o saldo
     *                                                 (`extra_minutes`) continua a ser recalculado. Use false após
     *                                                 correções de ponto, backfills ou recálculos mensais.
     */
    public function calculateAndSave(
        Employee $employee,
        string $date,
        bool $preserveClosedToleranceSnapshot = false,
    ): WorkDay {
        // Garantir que departamento e escala individual estão carregados
        $employee->loadMissing(['workSchedule', 'dept', 'company']);

        $existing = WorkDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        // Os datetimes estão guardados no fuso local (não UTC) — usar whereDate directamente
        $records = $employee->timeRecords()
            ->whereDate('datetime', $date)
            ->orderBy('datetime')
            ->get();

        $data = $this->calculate($employee, $records, $date);

        $this->logToleranceMismatchRuntime($employee->id, $date, $data);

        $shouldFreezeSnapshot = $preserveClosedToleranceSnapshot
            && $existing
            && $existing->is_closed
            && $existing->hasValidToleranceSnapshot()
            && ($data['is_closed'] ?? false);

        if ($shouldFreezeSnapshot) {
            $data['tolerance_snapshot'] = $existing->tolerance_snapshot;
            $this->logFrozenSnapshotDrift($employee->id, $date, $data);
        }

        $workDay = WorkDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if (! $workDay) {
            $workDay = new WorkDay([
                'employee_id' => $employee->id,
                'date' => Carbon::parse($date)->format('Y-m-d'),
            ]);
        }

        $workDay->fill($data);
        $workDay->save();

        $workDay = $workDay->fresh();

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
        $dates = collect([
            $edit->original_datetime?->toDateString(),
            $edit->new_datetime?->toDateString(),
        ])->filter()->unique()->values();
        foreach ($dates as $date) {
            $this->calculateAndSave($employee, $date, false);
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
            ? 'Hora extra em '.$workDay->date->format('d/m/Y')
            : 'Saída antecipada em '.$workDay->date->format('d/m/Y');

        HourBankTransaction::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_day_id' => $workDay->id,
            ],
            [
                'type' => $type,
                'minutes' => $extra,
                'description' => $description,
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
        $employee->loadMissing(['workSchedule', 'dept', 'company']);

        $schedule = $employee->workSchedule;
        $dept = $employee->dept;
        $deptRef = ($dept && $dept->entry_time && $dept->exit_time) ? $dept : null;

        $tz = WorkToleranceResolver::effectiveTimezone($employee->company);
        $calendarCarbon = Carbon::parse($date.' 12:00:00', $tz);
        $ctx = $this->toleranceResolver->resolve($employee, $calendarCarbon);

        // Dia da semana (0=Dom … 6=Sáb)
        $dayOfWeek = (int) Carbon::parse($date, $tz)->format('w');

        // Dias de trabalho configurados (departamento ou escala individual)
        $configuredWorkDays = $deptRef
            ? $deptRef->workDaysList()
            : ($schedule?->workDaysList() ?? [1, 2, 3, 4, 5]);

        // Verificar se é dia de trabalho configurado
        $isConfiguredWorkDay = in_array($dayOfWeek, $configuredWorkDays);

        // Feriado e dia especial
        $isHoliday = Holiday::isHoliday($date, $employee->company_id);
        $isSunday = ($dayOfWeek === 0);
        $isSaturday = ($dayOfWeek === 6);

        // Separar entradas e saídas — datetimes já em horário local
        $firstEntry = $records->firstWhere('type', 'entrada');
        $lastExit = $records->filter(fn ($r) => $r->type === 'saida')->last();

        $entryTime = $firstEntry?->datetime?->format('H:i:s');
        $exitTime = $lastExit?->datetime?->format('H:i:s');

        // Tempo trabalhado: soma de todos os pares entrada→saída consecutivos.
        // O intervalo de almoço é deduzido naturalmente (saída/retorno de almoço).
        $totalMinutes = 0;
        $totalIntervals = 0;
        $openEntryAt = null;
        $firstExitAt = null;

        foreach ($records as $record) {
            if ($record->type === 'entrada') {
                if ($firstExitAt !== null) {
                    $totalIntervals += (int) abs($record->datetime->diffInRealMinutes($firstExitAt));
                    $firstExitAt = null;
                }
                $openEntryAt = $record->datetime;
            } elseif ($record->type === 'saida' && $openEntryAt !== null) {
                $totalMinutes += (int) abs($record->datetime->diffInRealMinutes($openEntryAt));
                $firstExitAt = $record->datetime;
                $openEntryAt = null;
            }
        }

        // Minutos esperados — apenas para dias de trabalho configurados
        // Em dias fora da jornada (folga, sáb/dom não configurados) esperado = 0
        $expectedMinutes = 0;
        if ($isConfiguredWorkDay && ! $isHoliday && ! $isSunday) {
            $expectedMinutes = $deptRef
                ? $deptRef->getExpectedMinutesForDay($dayOfWeek)
                : ($schedule?->getExpectedMinutes() ?? $employee->dailyExpectedMinutes());
        }

        $totalStored = max(0, $totalMinutes);
        $diff = $totalMinutes - $expectedMinutes;

        $isClosed = $lastExit !== null;
        $extraMinutes = 0;
        $toleranceSnapshot = [];

        if (! $isClosed) {
            $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, [
                'calculation_path' => 'open_day',
                'total_minutes' => $totalStored,
                'total_minutes_raw' => $totalMinutes,
                'expected_minutes' => $expectedMinutes,
                'raw_diff_minutes' => $diff,
                'extra_minutes_final' => 0,
                'day_closed' => false,
            ]);
        } elseif ($isHoliday || $isSunday) {
            $extraMinutes = $totalMinutes;
            $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, [
                'calculation_path' => 'holiday_or_sunday_full',
                'total_minutes' => $totalStored,
                'total_minutes_raw' => $totalMinutes,
                'expected_minutes' => $expectedMinutes,
                'raw_diff_minutes' => $diff,
                'extra_minutes_final' => $extraMinutes,
                'day_closed' => true,
            ]);
        } elseif (! $isConfiguredWorkDay || $isSaturday) {
            $extraMinutes = $totalMinutes;
            $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, [
                'calculation_path' => 'saturday_or_off_schedule_full',
                'total_minutes' => $totalStored,
                'total_minutes_raw' => $totalMinutes,
                'expected_minutes' => $expectedMinutes,
                'raw_diff_minutes' => $diff,
                'extra_minutes_final' => $extraMinutes,
                'day_closed' => true,
            ]);
        } else {
            $extraMinutes = $this->toleranceResolver->applyToleranceToDiff(
                $diff,
                $ctx->toleranceMinutes,
                $ctx->toleranceMode
            );
            $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, [
                'calculation_path' => 'weekday_tolerance',
                'total_minutes' => $totalStored,
                'total_minutes_raw' => $totalMinutes,
                'expected_minutes' => $expectedMinutes,
                'raw_diff_minutes' => $diff,
                'extra_minutes_final' => $extraMinutes,
                'day_closed' => true,
            ]);
        }

        return [
            'entry_time' => $entryTime,
            'lunch_start' => null,
            'lunch_end' => null,
            'exit_time' => $exitTime,
            'total_minutes' => $totalStored,
            'expected_minutes' => $expectedMinutes,
            'extra_minutes' => $extraMinutes,
            'lunch_minutes' => $totalIntervals,
            'is_closed' => $isClosed,
            'tolerance_snapshot' => $toleranceSnapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $pathFields  calculation_path, totals, extra_minutes_final, day_closed, …
     * @return array<string, mixed>
     */
    private function mergeToleranceSnapshot(WorkToleranceContext $ctx, array $pathFields): array
    {
        return array_merge([
            'version' => WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION,
            'engine' => WorkDay::TOLERANCE_ENGINE_ID,
            'mode' => $ctx->toleranceMode,
            'minutes' => $ctx->toleranceMinutes,
            'source' => $ctx->modeResolvedFrom,
            'mode_label_pt' => $this->toleranceModeLabelPt($ctx->toleranceMode),
            'source_label_pt' => $this->toleranceSourceLabelPt($ctx->modeResolvedFrom),
            'timezone' => $ctx->timezone,
            'calendar_date' => $ctx->calendarDate,
        ], $pathFields);
    }

    private function toleranceSourceLabelPt(string $source): string
    {
        return match ($source) {
            WorkToleranceContext::SOURCE_DEPARTMENT => 'Departamento (gabarito)',
            WorkToleranceContext::SOURCE_WORK_SCHEDULE => 'Escala individual',
            WorkToleranceContext::SOURCE_COMPANY => 'Empresa',
            default => 'Padrão do sistema',
        };
    }

    private function toleranceModeLabelPt(string $mode): string
    {
        return match ($mode) {
            WorkToleranceResolver::MODE_DAILY_DISCOUNT => 'Desconto no saldo diário',
            default => 'Faixa neutra (dead band)',
        };
    }

    /** Coerência entre coluna `extra_minutes` e snapshot recém-calculado (antes de congelar JSON). */
    private function logToleranceMismatchRuntime(int $employeeId, string $date, array $data): void
    {
        $snap = $data['tolerance_snapshot'] ?? [];
        if (! is_array($snap) || ! array_key_exists('extra_minutes_final', $snap)) {
            return;
        }
        if ((int) $data['extra_minutes'] !== (int) $snap['extra_minutes_final']) {
            Log::warning('tolerance_mismatch_runtime', [
                'employee_id' => $employeeId,
                'date' => $date,
                'extra_minutes' => $data['extra_minutes'],
                'snapshot_extra_minutes_final' => $snap['extra_minutes_final'],
            ]);
        }
    }

    /** Snapshot congelado diverge do saldo recalculado (esperado se regras ou pontos mudaram). */
    private function logFrozenSnapshotDrift(int $employeeId, string $date, array $data): void
    {
        $frozen = data_get($data['tolerance_snapshot'], 'extra_minutes_final');
        if ($frozen === null) {
            return;
        }
        if ((int) $data['extra_minutes'] !== (int) $frozen) {
            Log::warning('tolerance_frozen_snapshot_drift', [
                'employee_id' => $employeeId,
                'date' => $date,
                'computed_extra_minutes' => $data['extra_minutes'],
                'frozen_snapshot_extra_minutes_final' => $frozen,
            ]);
        }
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
