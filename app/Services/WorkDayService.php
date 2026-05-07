<?php

namespace App\Services;

use App\DTO\WorkToleranceContext;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HourBankTransaction;
use App\Models\TimeRecordEdit;
use App\Models\WorkDay;
use App\Models\WorkSchedule;
use App\Support\CltSkipReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WorkDayService
{
    public function __construct(
        private readonly WorkToleranceResolver $toleranceResolver,
        private readonly CltToleranceEngine $cltToleranceEngine,
    ) {}

    /**
     * @param  bool  $preserveClosedToleranceSnapshot  Se true e o dia já estava fechado com snapshot válido,
     *                                                 mantém o JSON de auditoria (evita drift em replays); o saldo
     *                                                 (`extra_minutes`) continua a ser recalculado. Use false após
     *                                                 correções de ponto, backfills ou migração explícita de regra.
     * @param  array<string, mixed>|null  $recalculationAudit  Campos extra fundidos no snapshot após recálculo (ex.: CLI).
     *                                                         Só aplica quando o snapshot **não** fica congelado e há snapshot novo não vazio.
     */
    public function calculateAndSave(
        Employee $employee,
        string $date,
        bool $preserveClosedToleranceSnapshot = false,
        ?array $recalculationAudit = null,
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
        } elseif (
            $recalculationAudit !== null
            && is_array($data['tolerance_snapshot'] ?? null)
            && ($data['tolerance_snapshot'] ?? []) !== []
        ) {
            $data['tolerance_snapshot'] = array_merge(
                $data['tolerance_snapshot'],
                $recalculationAudit,
                ['recalculated_to_engine' => data_get($data['tolerance_snapshot'], 'engine')],
            );
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

        $this->logWorkdayToleranceTelemetry($workDay);

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
            $extraMinutes = 0;
            $toleranceSnapshot = [];
            $template = null;
            $cltSkipReason = null;

            if ($this->isCltEventToleranceMode($ctx->toleranceMode)) {
                $template = $this->buildCltEventTemplate($deptRef, $schedule, $dayOfWeek);
                if ($template === null) {
                    $cltSkipReason = CltSkipReason::MISSING_GABARITO;
                } else {
                    $pair = $this->resolveCltPairing($records, $template);
                    if ($pair['times'] !== null) {
                        $strictLunchReturn = $ctx->toleranceMode === WorkToleranceResolver::MODE_CLT_EVENT_STRICT;
                        $progressiveCap = $ctx->toleranceMode === WorkToleranceResolver::MODE_CLT_EVENT_PROGRESSIVE_CAP;
                        $lunchMinStrict = $this->resolveLunchMinutesForCltStrict($deptRef, $schedule, $dayOfWeek);

                        // Fusão do almoço em um só desvio (duração): faz sentido no CLT "based" (bucket linear).
                        // No progressive cap, menos eventos mudam a ordem das liberações do bucket e pode alterar
                        // muito o saldo vs histórico — mantém-se o modelo de 4 marcas × gabarito.
                        $mergeLunchAsDuration = ! $strictLunchReturn
                            && ! $progressiveCap
                            && count($template) === 4
                            && $lunchMinStrict > 0;

                        $slots = $this->buildCltSlotsForEngine(
                            $date,
                            $tz,
                            $template,
                            $pair['times'],
                            $strictLunchReturn,
                            $lunchMinStrict,
                            $mergeLunchAsDuration,
                        );
                        $enginePack = $progressiveCap
                            ? $this->cltToleranceEngine->calculateProgressiveDailyCap($slots)
                            : $this->cltToleranceEngine->calculate($slots);
                        $extraMinutes = $enginePack['bank_minutes'];
                        $cltBlock = $enginePack['clt'];

                        if ($strictLunchReturn && count($template) === 4 && $lunchMinStrict > 0) {
                            $cltBlock['lunch_return_expected_source'] = 'actual_lunch_exit_plus_duration';
                        } elseif ($mergeLunchAsDuration) {
                            $cltBlock['lunch_return_expected_source'] = 'actual_lunch_exit_plus_configured_duration';
                            $cltBlock['lunch_interval_semantics_pt'] = 'Intervalo de almoço como um único desvio: '
                                .'horário previsto de retorno = saída real para almoço + intervalo configurado; '
                                .'delta = duração real do intervalo − minutos configurados.';
                        } elseif (count($template) === 4) {
                            $cltBlock['lunch_return_expected_source'] = 'gabarito';
                        }

                        $isStrictPath = $ctx->toleranceMode === WorkToleranceResolver::MODE_CLT_EVENT_STRICT;
                        $isProgressivePath = $progressiveCap;
                        $engineConst = match (true) {
                            $isStrictPath => WorkDay::TOLERANCE_ENGINE_CLT_EVENT_STRICT,
                            $isProgressivePath => WorkDay::TOLERANCE_ENGINE_CLT_PROGRESSIVE_CAP,
                            default => WorkDay::TOLERANCE_ENGINE_CLT_EVENT_BASED,
                        };
                        $calcPath = match (true) {
                            $isStrictPath => 'weekday_clt_event_strict',
                            $isProgressivePath => 'weekday_clt_event_progressive_cap',
                            default => 'weekday_clt_event_based',
                        };
                        $calcBasePt = match (true) {
                            $isStrictPath => 'Eventos de ponto — retorno do almoço pela saída real + duração configurada',
                            $isProgressivePath => 'Eventos de ponto — tolerância progressiva (bucket 5+10 com liberação)',
                            default => 'Eventos de ponto — entrada e saída final × gabarito; intervalo de almoço × duração '
                                .'(saída real + minutos configurados)',
                        };
                        $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, [
                            'engine' => $engineConst,
                            'calculation_path' => $calcPath,
                            'lunch_configured_minutes' => count($template) === 4 ? $lunchMinStrict : null,
                            'integration_mode' => 'clt_primary',
                            'calculation_base_pt' => $calcBasePt,
                            'total_minutes' => $totalStored,
                            'total_minutes_raw' => $totalMinutes,
                            'expected_minutes' => $expectedMinutes,
                            'raw_diff_minutes' => $diff,
                            'extra_minutes_final' => $extraMinutes,
                            'expected_events' => count($template),
                            'actual_events' => $records->count(),
                            'event_tolerance_minutes' => $enginePack['event_tolerance_minutes'],
                            'daily_cap_minutes' => $enginePack['daily_cap_minutes'],
                            'clt_bucket_sum' => $enginePack['clt_bucket_sum'],
                            'clt_bucket_result' => $enginePack['clt_bucket_result'],
                            'outside_event_sum' => $enginePack['outside_event_sum'],
                            'events' => $enginePack['events'],
                            'clt_result_minutes' => $cltBlock['result_minutes_from_clt_small_bucket'],
                            'outside_event_minutes' => $cltBlock['outside_event_tolerance_sum'],
                            'clt_applied' => true,
                            'clt_skipped' => false,
                            'clt_skip_reason' => null,
                            'day_closed' => true,
                            'clt' => $cltBlock,
                        ]);
                    } else {
                        $cltSkipReason = CltSkipReason::normalize((string) ($pair['skip_reason'] ?? CltSkipReason::UNKNOWN));
                    }
                }
            }

            if ($toleranceSnapshot === []) {
                $extraMinutes = $this->toleranceResolver->applyToleranceToDiff(
                    $diff,
                    $ctx->toleranceMinutes,
                    $ctx->toleranceMode
                );
                $pathPayload = [
                    'calculation_path' => 'weekday_tolerance',
                    'total_minutes' => $totalStored,
                    'total_minutes_raw' => $totalMinutes,
                    'expected_minutes' => $expectedMinutes,
                    'raw_diff_minutes' => $diff,
                    'extra_minutes_final' => $extraMinutes,
                    'day_closed' => true,
                ];
                if ($this->isCltEventToleranceMode($ctx->toleranceMode)) {
                    $reason = CltSkipReason::normalize((string) ($cltSkipReason ?? CltSkipReason::UNKNOWN));
                    $pathPayload['integration_mode'] = 'diff_fallback_after_clt_skip';
                    $pathPayload['calculation_base_pt'] = 'Saldo diário (trabalhado − esperado) com tolerância por faixa/desconto — CLT por batida não aplicado.';
                    $pathPayload['clt_applied'] = false;
                    $pathPayload['clt_skipped'] = true;
                    $pathPayload['clt_skip_reason'] = $reason;
                    $pathPayload['clt_skip_category'] = CltSkipReason::category($reason);
                    $pathPayload['expected_events'] = $template !== null ? count($template) : null;
                    $pathPayload['actual_events'] = $records->count();
                    $pathPayload['clt_skip_detail'] = [
                        'reason_label_pt' => CltSkipReason::labelPt($reason),
                        'skip_category' => CltSkipReason::category($reason),
                        'expected_slots' => $template !== null ? count($template) : null,
                        'records_count' => $records->count(),
                    ];
                }
                $toleranceSnapshot = $this->mergeToleranceSnapshot($ctx, $pathPayload);
            }
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
     * Monta o snapshot de tolerância; acrescenta `effective_tolerance_engine_family` e `policy.calculation.family`
     * (família matemática estável para BI/mobile), sempre derivados de `calculation_path`.
     *
     * @param  array<string, mixed>  $pathFields  calculation_path, totals, extra_minutes_final, day_closed, …
     * @return array<string, mixed>
     */
    private function mergeToleranceSnapshot(WorkToleranceContext $ctx, array $pathFields): array
    {
        $merged = array_merge([
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

        $merged['effective_tolerance_engine_family'] = WorkDay::effectiveToleranceEngineFamily($merged);
        $merged['calculation_confidence'] = WorkDay::toleranceConfidenceFromSnapshot($merged);
        $merged['policy'] = $this->buildTolerancePolicyContract($merged);

        return $merged;
    }

    /**
     * Contrato institucional (`policy`): visão normalizada só para leitura — não altera regras de cálculo.
     * Derivado exclusivamente de chaves já presentes em `$merged` após o merge do snapshot.
     * Inclui `generated_from_snapshot_version` (= `$merged['version']`) para correlacionar contrato `policy` com evoluções futuras do schema JSON.
     *
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function buildTolerancePolicyContract(array $merged): array
    {
        $eventMinutes = data_get($merged, 'event_tolerance_minutes');
        if ($eventMinutes === null) {
            $eventMinutes = data_get($merged, 'clt.event_tolerance_minutes');
        }
        $dailyCapMinutes = data_get($merged, 'daily_cap_minutes');
        if ($dailyCapMinutes === null) {
            $dailyCapMinutes = data_get($merged, 'clt.daily_cap_minutes');
        }

        $lunchStrategy = data_get($merged, 'clt.lunch_return_expected_source');
        $lunchConfigured = data_get($merged, 'lunch_configured_minutes');
        if ($lunchConfigured === null) {
            $lunchConfigured = data_get($merged, 'clt.lunch_configured_minutes');
        }

        $integrationMode = array_key_exists('integration_mode', $merged)
            ? $merged['integration_mode']
            : null;

        return [
            'version' => WorkDay::TOLERANCE_POLICY_CONTRACT_VERSION,
            'generated_from_snapshot_version' => isset($merged['version']) ? (int) $merged['version'] : null,
            'mode' => (string) ($merged['mode'] ?? ''),
            'engine' => (string) ($merged['engine'] ?? ''),
            'tolerance' => [
                'daily_minutes' => isset($merged['minutes']) ? (int) $merged['minutes'] : null,
                'event_minutes' => $eventMinutes !== null ? (int) $eventMinutes : null,
                'daily_cap_minutes' => $dailyCapMinutes !== null ? (int) $dailyCapMinutes : null,
            ],
            'integration' => [
                'mode' => is_string($integrationMode) ? $integrationMode : null,
                'fallback_mode' => null,
            ],
            'lunch' => [
                'strategy' => is_string($lunchStrategy) ? $lunchStrategy : null,
                'configured_minutes' => $lunchConfigured !== null ? (int) $lunchConfigured : null,
            ],
            'timezone' => isset($merged['timezone']) && is_string($merged['timezone']) ? $merged['timezone'] : null,
            'calculation' => [
                'path' => isset($merged['calculation_path']) && is_string($merged['calculation_path'])
                    ? $merged['calculation_path']
                    : null,
                'confidence' => isset($merged['calculation_confidence']) && is_string($merged['calculation_confidence'])
                    ? $merged['calculation_confidence']
                    : null,
                'family' => isset($merged['effective_tolerance_engine_family']) && is_string($merged['effective_tolerance_engine_family'])
                    ? $merged['effective_tolerance_engine_family']
                    : WorkDay::effectiveToleranceEngineFamily($merged),
            ],
        ];
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
            WorkToleranceResolver::MODE_CLT_EVENT_BASED => 'CLT por batida (5+10)',
            WorkToleranceResolver::MODE_CLT_EVENT_STRICT => 'CLT por batida (5+10, retorno por duração)',
            WorkToleranceResolver::MODE_CLT_EVENT_PROGRESSIVE_CAP => 'CLT por batida — bucket progressivo (5+10)',
            default => 'Faixa neutra (dead band)',
        };
    }

    private function isCltEventToleranceMode(string $mode): bool
    {
        return in_array($mode, [
            WorkToleranceResolver::MODE_CLT_EVENT_BASED,
            WorkToleranceResolver::MODE_CLT_EVENT_STRICT,
            WorkToleranceResolver::MODE_CLT_EVENT_PROGRESSIVE_CAP,
        ], true);
    }

    private function resolveLunchMinutesForCltStrict(?Department $deptRef, ?WorkSchedule $schedule, int $dayOfWeek): int
    {
        if ($deptRef !== null) {
            return max(0, $deptRef->getLunchMinutesForDay($dayOfWeek));
        }

        return max(0, (int) ($schedule?->lunch_minutes ?? 0));
    }

    /**
     * Monta slots para o motor CLT.
     *
     * Quando `$mergeLunchAsDuration` é verdadeiro (**somente** modo CLT based neste serviço; não progressive cap):
     * em vez de comparar saída para almoço e retorno aos horários fixos do gabarito, usa **um único evento**
     * `lunch_duration`: previsto de retorno = saída real para almoço + intervalo configurado (delta = duração real − configurada).
     *
     * @param  list<array{type: string, time: string}>  $template
     * @param  list<Carbon>  $actualCarbons
     * @return list<array{semantic_type: string, expected: Carbon, actual: Carbon}>
     */
    private function buildCltSlotsForEngine(
        string $date,
        string $tz,
        array $template,
        array $actualCarbons,
        bool $strictLunchReturn,
        int $configuredLunchMinutes,
        bool $mergeLunchAsDuration,
    ): array {
        if ($mergeLunchAsDuration && count($template) === 4 && count($actualCarbons) === 4) {
            return [
                [
                    'semantic_type' => 'entry',
                    'expected' => Carbon::parse(trim($date).' '.trim((string) $template[0]['time']), $tz),
                    'actual' => $actualCarbons[0],
                ],
                [
                    'semantic_type' => 'lunch_duration',
                    'expected' => $actualCarbons[1]->copy()->addMinutes($configuredLunchMinutes),
                    'actual' => $actualCarbons[2],
                ],
                [
                    'semantic_type' => 'final_out',
                    'expected' => Carbon::parse(trim($date).' '.trim((string) $template[3]['time']), $tz),
                    'actual' => $actualCarbons[3],
                ],
            ];
        }

        $semanticFour = ['entry', 'lunch_out', 'lunch_return', 'final_out'];
        $semanticTwo = ['entry', 'final_out'];
        $semantic = count($template) === 4 ? $semanticFour : $semanticTwo;

        $slots = [];
        foreach ($template as $i => $slot) {
            $actual = $actualCarbons[$i];
            if ($strictLunchReturn && count($template) === 4 && $i === 2 && $configuredLunchMinutes > 0) {
                $expected = $actualCarbons[1]->copy()->addMinutes($configuredLunchMinutes);
            } else {
                $expected = Carbon::parse(trim($date).' '.trim((string) $slot['time']), $tz);
            }

            $slots[] = [
                'semantic_type' => $semantic[$i] ?? (string) $slot['type'],
                'expected' => $expected,
                'actual' => $actual,
            ];
        }

        return $slots;
    }

    /**
     * Gabarito CLT: 2 batidas (sem intervalo de almoço) ou 4 batidas (com almoço).
     *
     * @return list<array{type: string, time: string}>|null
     */
    private function buildCltEventTemplate(?Department $deptRef, ?WorkSchedule $schedule, int $dayOfWeek): ?array
    {
        if ($deptRef !== null && $deptRef->entry_time && $deptRef->exit_time) {
            $lunch = $deptRef->getLunchMinutesForDay($dayOfWeek);
            if ($lunch <= 0) {
                return $this->twoSlotCltTemplate((string) $deptRef->entry_time, (string) $deptRef->exit_time);
            }
            $g = $deptRef->getGabaritoTimesForDay($dayOfWeek);
            if (! is_array($g) || ! isset($g['e1'], $g['s1'], $g['e2'], $g['s2'])) {
                return null;
            }

            return $this->fourSlotCltTemplate($g);
        }
        if ($schedule !== null && $schedule->entry_time && $schedule->exit_time) {
            $lunch = (int) ($schedule->lunch_minutes ?? 0);
            if ($lunch <= 0) {
                return $this->twoSlotCltTemplate((string) $schedule->entry_time, (string) $schedule->exit_time);
            }
            $g = $schedule->getGabaritoTimes();
            if (! is_array($g) || ! isset($g['e1'], $g['s1'], $g['e2'], $g['s2'])) {
                return null;
            }

            return $this->fourSlotCltTemplate($g);
        }

        return null;
    }

    /**
     * @param  array{e1: string, s1: string, e2: string, s2: string}  $g
     * @return list<array{type: string, time: string}>
     */
    private function fourSlotCltTemplate(array $g): array
    {
        return [
            ['type' => 'entrada', 'time' => (string) $g['e1']],
            ['type' => 'saida', 'time' => (string) $g['s1']],
            ['type' => 'entrada', 'time' => (string) $g['e2']],
            ['type' => 'saida', 'time' => (string) $g['s2']],
        ];
    }

    /**
     * @return list<array{type: string, time: string}>
     */
    private function twoSlotCltTemplate(string $entryTime, string $exitTime): array
    {
        return [
            ['type' => 'entrada', 'time' => $this->normalizeClockForCltTemplate($entryTime)],
            ['type' => 'saida', 'time' => $this->normalizeClockForCltTemplate($exitTime)],
        ];
    }

    private function normalizeClockForCltTemplate(string $clock): string
    {
        return Carbon::parse(trim($clock))->format('H:i');
    }

    /**
     * Pareamento por ordem temporal + tipo (robustez maior = futuro: proximidade por tipo).
     *
     * @param  iterable<mixed>  $records
     * @param  list<array{type: string, time: string}>  $template
     * @return array{times: list<Carbon>|null, skip_reason: string|null}
     */
    private function resolveCltPairing(iterable $records, array $template): array
    {
        $n = count($template);
        if (! in_array($n, [2, 4], true)) {
            return ['times' => null, 'skip_reason' => CltSkipReason::UNKNOWN];
        }

        $sorted = collect($records)->sortBy(fn ($r) => $r->datetime->timestamp)->values();
        if ($sorted->count() !== $n) {
            return ['times' => null, 'skip_reason' => CltSkipReason::WRONG_RECORD_COUNT];
        }

        $out = [];
        foreach ($template as $i => $slot) {
            $r = $sorted[$i];
            if ($r->type !== $slot['type']) {
                return ['times' => null, 'skip_reason' => CltSkipReason::TYPE_SEQUENCE_MISMATCH];
            }
            $out[] = $r->datetime;
        }

        return ['times' => $out, 'skip_reason' => null];
    }

    /**
     * Observabilidade em produção (diff vs CLT, fallback, confiança) — após persistir o {@see WorkDay}.
     */
    private function logWorkdayToleranceTelemetry(WorkDay $workDay): void
    {
        $snap = $workDay->tolerance_snapshot ?? [];
        if (! is_array($snap)) {
            return;
        }

        $mode = (string) ($snap['mode'] ?? '');
        $engineType = $this->isCltEventToleranceMode($mode) ? 'clt' : 'diff';

        Log::info('workday_tolerance', [
            'work_day_id' => $workDay->id,
            'employee_id' => $workDay->employee_id,
            'date' => $workDay->date?->toDateString(),
            'meta_version' => WorkDay::TOLERANCE_META_API_VERSION,
            'engine_type' => $engineType,
            'tolerance_mode' => $mode !== '' ? $mode : null,
            'calculation_path' => $snap['calculation_path'] ?? null,
            'clt_applied' => $snap['clt_applied'] ?? null,
            'clt_skipped' => $snap['clt_skipped'] ?? null,
            'clt_skip_reason' => $snap['clt_skip_reason'] ?? null,
            'clt_skip_category' => $snap['clt_skip_category'] ?? null,
            'expected_events' => $snap['expected_events'] ?? null,
            'actual_events' => $snap['actual_events'] ?? null,
            'clt_bucket_sum' => $snap['clt_bucket_sum'] ?? null,
            'outside_event_sum' => $snap['outside_event_sum'] ?? null,
            'calculation_confidence' => $snap['calculation_confidence'] ?? null,
            'effective_tolerance_engine_family' => $snap['effective_tolerance_engine_family'] ?? null,
            'extra_minutes' => $workDay->extra_minutes,
        ]);
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
