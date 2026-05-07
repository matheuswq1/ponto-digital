<?php

namespace App\Models;

use App\Services\WorkToleranceResolver;
use App\Support\CltSkipReason;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDay extends Model
{
    use HasFactory;

    /**
     * Atributos derivados do snapshot para BI / APIs (não são colunas).
     *
     * @var list<string>
     */
    protected $appends = [
        'tt_engine',
        'tt_mode',
        'tt_clt_applied',
        'tt_calculation_confidence',
    ];

    /** Versão do esquema do JSON em tolerance_snapshot (migrações futuras sem invalidar histórico). */
    public const TOLERANCE_SNAPSHOT_SCHEMA_VERSION = 1;

    /** Versão do contrato normalizado `policy` dentro do snapshot (consumidores externos / BI / mobile). */
    public const TOLERANCE_POLICY_CONTRACT_VERSION = 1;

    /**
     * Família matemática estável para BI / mobile / API — derivada só do snapshot persistido
     * ({@see self::effectiveToleranceEngineFamily()}), sem acoplamento a modo/engine específicos.
     */
    public const EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT = 'clt_event';

    public const EFFECTIVE_TOLERANCE_ENGINE_FAMILY_DAILY_DIFF = 'daily_diff';

    public const EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CALENDAR_FULL_DAY = 'calendar_full_day';

    public const EFFECTIVE_TOLERANCE_ENGINE_FAMILY_OPEN_DAY = 'open_day';

    public const EFFECTIVE_TOLERANCE_ENGINE_FAMILY_UNKNOWN = 'unknown';

    /** Identificador do motor que gerou o snapshot (troca de algoritmo / comparação / rollback lógico). */
    public const TOLERANCE_ENGINE_ID = 'v1';

    /** Motor CLT por batida (based): entrada/saída final × gabarito; intervalo de almoço × duração a partir da saída real + minutos configurados. */
    public const TOLERANCE_ENGINE_CLT_EVENT_BASED = 'v2_clt_event_based';

    /** Motor CLT estrito (retorno do almoço por duração a partir da saída real). */
    public const TOLERANCE_ENGINE_CLT_EVENT_STRICT = 'v3_clt_event_engine';

    /** Motor CLT bucket progressivo / liberação ao atingir teto diário (±10 no bucket). */
    public const TOLERANCE_ENGINE_CLT_PROGRESSIVE_CAP = 'v4_clt_progressive_cap';

    /** Progressive cap com almoço como duração única (efeito jornada no delta do intervalo). */
    public const TOLERANCE_ENGINE_CLT_PROGRESSIVE_DURATION = 'v5_clt_progressive_duration';

    /** Versão do bloco `tolerance_meta` na API — incrementar só com mudança compatível ou novo contrato documentado. */
    public const TOLERANCE_META_API_VERSION = 2;

    /**
     * Contrato estável para apps (`tt_kind`): não remover valores; só acrescentar novos no futuro.
     *
     * @see self::toleranceUxKind()
     */
    public const TT_KIND_WITHIN = 'within';

    public const TT_KIND_APPLIED_DISCOUNT = 'applied_discount';

    public const TT_KIND_OUTSIDE_DEAD_BAND = 'outside_dead_band';

    /** Sem classificação de tolerância diária para UX (ex.: feriado, outros `calculation_path`). */
    public const TT_KIND_NONE = 'none';

    protected $fillable = [
        'employee_id',
        'date',
        'entry_time',
        'lunch_start',
        'lunch_end',
        'exit_time',
        'total_minutes',
        'expected_minutes',
        'extra_minutes',
        'lunch_minutes',
        'status',
        'observations',
        'is_closed',
        'tolerance_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_minutes' => 'integer',
            'expected_minutes' => 'integer',
            'extra_minutes' => 'integer',
            'lunch_minutes' => 'integer',
            'is_closed' => 'boolean',
            'tolerance_snapshot' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Motor de tolerância aplicado (espelho de tolerance_snapshot.engine). */
    protected function ttEngine(): Attribute
    {
        return Attribute::get(fn (): ?string => data_get($this->tolerance_snapshot, 'engine'));
    }

    /** Modo CLT por batida aplicado neste dia (`true` só quando pareamento + gabarito OK). */
    protected function ttCltApplied(): Attribute
    {
        return Attribute::get(fn (): bool => (bool) data_get($this->tolerance_snapshot, 'clt_applied'));
    }

    /** Modo de tolerância aplicado (espelho de tolerance_snapshot.mode). */
    protected function ttMode(): Attribute
    {
        return Attribute::get(fn (): ?string => data_get($this->tolerance_snapshot, 'mode'));
    }

    /**
     * Confiança interpretativa do resultado (`high` CLT aplicado, `low` fallback CLT ou dia aberto, `medium` demais).
     *
     * @see self::toleranceConfidenceFromSnapshot()
     */
    protected function ttCalculationConfidence(): Attribute
    {
        return Attribute::get(fn (): string => self::toleranceConfidenceFromSnapshot(
            is_array($this->tolerance_snapshot) ? $this->tolerance_snapshot : []
        ));
    }

    /**
     * Família efetiva de cálculo (contrato estável). Depende apenas de `calculation_path` no snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function effectiveToleranceEngineFamily(array $snapshot): string
    {
        $path = (string) ($snapshot['calculation_path'] ?? '');

        if (str_starts_with($path, 'weekday_clt_event')) {
            return self::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT;
        }

        if ($path === 'weekday_tolerance') {
            return self::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_DAILY_DIFF;
        }

        if ($path === 'holiday_or_sunday_full' || $path === 'saturday_or_off_schedule_full') {
            return self::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CALENDAR_FULL_DAY;
        }

        if ($path === 'open_day') {
            return self::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_OPEN_DAY;
        }

        return self::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_UNKNOWN;
    }

    /**
     * Heurística única para snapshot persistido e para accessors — mantém BI e API alinhados.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function toleranceConfidenceFromSnapshot(array $snapshot): string
    {
        $path = (string) ($snapshot['calculation_path'] ?? '');
        if ($path === 'open_day') {
            return 'low';
        }
        if (($snapshot['clt_skipped'] ?? false) === true) {
            $cat = (string) ($snapshot['clt_skip_category'] ?? '');

            return $cat === CltSkipReason::CATEGORY_RULE ? 'medium' : 'low';
        }
        if (in_array($path, ['weekday_clt_event_based', 'weekday_clt_event_strict', 'weekday_clt_event_progressive_cap', 'weekday_clt_event_progressive_duration'], true) && ($snapshot['clt_applied'] ?? false)) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Contrato estável de leitura para apps (`tolerance_meta` na API) — derivado apenas do snapshot persistido.
     * Campos espelhados em `policy` usam dual-read (preferência ao contrato); o restante permanece só no legado.
     *
     * @return array<string, mixed>
     */
    public function toleranceMetaForApi(): array
    {
        $s = is_array($this->tolerance_snapshot) ? $this->tolerance_snapshot : [];

        $eventTol = data_get($s, 'policy.tolerance.event_minutes');
        if ($eventTol === null) {
            $eventTol = data_get($s, 'event_tolerance_minutes');
        }
        if ($eventTol === null) {
            $eventTol = data_get($s, 'clt.event_tolerance_minutes');
        }

        $dailyCap = data_get($s, 'policy.tolerance.daily_cap_minutes');
        if ($dailyCap === null) {
            $dailyCap = data_get($s, 'daily_cap_minutes');
        }
        if ($dailyCap === null) {
            $dailyCap = data_get($s, 'clt.daily_cap_minutes');
        }

        return [
            'meta_version' => self::TOLERANCE_META_API_VERSION,
            'is_complete' => $this->hasValidToleranceSnapshot(),
            // Contrato institucional (dual-write); snapshots antigos sem `policy` expõem null.
            'policy' => data_get($s, 'policy'),
            'engine' => data_get($s, 'policy.engine') ?? data_get($s, 'engine'),
            'mode' => data_get($s, 'policy.mode') ?? data_get($s, 'mode'),
            'calculation_path' => data_get($s, 'policy.calculation.path') ?? data_get($s, 'calculation_path'),
            'calculation_confidence' => data_get($s, 'policy.calculation.confidence') ?? data_get($s, 'calculation_confidence'),
            'effective_tolerance_engine_family' => data_get($s, 'policy.calculation.family')
                ?? data_get($s, 'effective_tolerance_engine_family')
                ?? self::effectiveToleranceEngineFamily($s),
            'expected_events' => data_get($s, 'expected_events'),
            'actual_events' => data_get($s, 'actual_events'),
            'clt_applied' => data_get($s, 'clt_applied'),
            'clt_skipped' => data_get($s, 'clt_skipped'),
            'clt_skip_reason' => data_get($s, 'clt_skip_reason'),
            'clt_skip_category' => data_get($s, 'clt_skip_category'),
            'integration_mode' => data_get($s, 'policy.integration.mode') ?? data_get($s, 'integration_mode'),
            'calculation_base_pt' => data_get($s, 'calculation_base_pt'),
            'event_tolerance_minutes' => $eventTol,
            'daily_cap_minutes' => $dailyCap,
            'clt_bucket_sum' => data_get($s, 'clt_bucket_sum', data_get($s, 'clt.sum_within_event_tolerance')),
            'outside_event_sum' => data_get($s, 'outside_event_sum', data_get($s, 'clt.outside_event_tolerance_sum')),
        ];
    }

    /** Soma do bucket CLT (marcações com |delta| ≤ 5 min) antes do teto diário — null se não aplicável. */
    public function cltBucketMinutes(): ?int
    {
        $s = is_array($this->tolerance_snapshot) ? $this->tolerance_snapshot : null;
        if ($s === null) {
            return null;
        }
        if (array_key_exists('clt_bucket_sum', $s)) {
            return (int) $s['clt_bucket_sum'];
        }

        return isset($s['clt']['sum_within_event_tolerance'])
            ? (int) $s['clt']['sum_within_event_tolerance']
            : null;
    }

    /** Soma dos desvios fora da tolerância por evento (|delta| > 5 min) — null se não aplicável. */
    public function outsideEventMinutes(): ?int
    {
        $s = is_array($this->tolerance_snapshot) ? $this->tolerance_snapshot : null;
        if ($s === null) {
            return null;
        }
        if (array_key_exists('outside_event_sum', $s)) {
            return (int) $s['outside_event_sum'];
        }

        return isset($s['clt']['outside_event_tolerance_sum'])
            ? (int) $s['clt']['outside_event_tolerance_sum']
            : null;
    }

    public function getTotalHoursAttribute(): float
    {
        return round($this->total_minutes / 60, 2);
    }

    public function getExtraHoursAttribute(): float
    {
        return round($this->extra_minutes / 60, 2);
    }

    public function isPositiveBalance(): bool
    {
        return $this->extra_minutes > 0;
    }

    public function isNegativeBalance(): bool
    {
        return $this->extra_minutes < 0;
    }

    public function getFormattedTotalAttribute(): string
    {
        $hours = intdiv(abs($this->total_minutes), 60);
        $minutes = abs($this->total_minutes) % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getFormattedExtraAttribute(): string
    {
        $sign = $this->extra_minutes < 0 ? '-' : '+';
        $hours = intdiv(abs($this->extra_minutes), 60);
        $minutes = abs($this->extra_minutes) % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    /** Formato HH:MM com sinal para UI (usa o caractere Unicode MINUS). */
    public static function formatSignedMinutesPt(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes > 0 ? '+' : '−';
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $mins = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $mins);
    }

    /**
     * Linhas curtas para exibir impacto real da tolerância (a partir do snapshot gravado no fecho).
     *
     * @return list<string>
     */
    public function toleranceImpactLinesPt(): array
    {
        $s = $this->tolerance_snapshot;
        if (! is_array($s) || $s === []) {
            return [];
        }

        $path = $s['calculation_path'] ?? '';
        $lines = [];

        if ($path === 'weekday_tolerance') {
            $raw = isset($s['raw_diff_minutes']) ? (int) $s['raw_diff_minutes'] : null;
            if ($raw !== null) {
                $lines[] = 'Saldo antes da tolerância: '.self::formatSignedMinutesPt($raw);
            }
            $tol = (int) ($s['minutes'] ?? 0);
            $modePt = (string) ($s['mode_label_pt'] ?? '');
            $lines[] = 'Tolerância aplicada: '.$tol.' min'.($modePt !== '' ? ' ('.$modePt.')' : '');
            $lines[] = 'Resultado no banco: '.self::formatSignedMinutesPt((int) ($s['extra_minutes_final'] ?? $this->extra_minutes));
            if (! empty($s['clt_skipped'])) {
                $code = (string) ($s['clt_skip_reason'] ?? '');
                $detail = is_array($s['clt_skip_detail'] ?? null) ? $s['clt_skip_detail'] : [];
                $label = (string) ($detail['reason_label_pt'] ?? $code);
                $lines[] = 'ATENÇÃO: modo CLT por batida não foi aplicado — '.$label;
                $cat = (string) ($s['clt_skip_category'] ?? '');
                if ($cat !== '') {
                    $lines[] = 'Categoria do skip: '.$cat.' (rule = cadastro/jornada · structural = registros do dia).';
                }
                $lines[] = 'Usado cálculo por saldo diário (faixa/desconto). Consulte clt_skip_reason no snapshot.';
            }
        } elseif ($path === 'weekday_clt_event_based' || $path === 'weekday_clt_event_strict' || $path === 'weekday_clt_event_progressive_cap' || $path === 'weekday_clt_event_progressive_duration') {
            $clt = $s['clt'] ?? [];
            $strictNote = $path === 'weekday_clt_event_strict'
                ? ' Retorno do almoço: saída real + duração configurada.'
                : '';
            $progNote = match ($path) {
                'weekday_clt_event_progressive_duration' => ' Bucket progressivo + almoço por duração (efeito jornada: intervalo menor → delta positivo no evento de almoço). Encerra tolerância como no modo progressivo clássico.',
                'weekday_clt_event_progressive_cap' => ' Bucket progressivo: 6–9 min → ±5 no bucket + resto no saldo; ≥10 ou |bucket|≥10 libera bucket e encerra tolerância do dia.',
                default => '',
            };
            $lines[] = 'Modo: CLT por marcação (5 / 10) · Base: eventos de ponto.'.$strictNote.$progNote;
            if (isset($s['integration_mode'])) {
                $lines[] = 'Integração: '.(string) $s['integration_mode'].' — resultado no banco pode diferir de trabalhado − esperado.';
            }
            if (isset($s['clt_result_minutes'], $s['outside_event_minutes'])) {
                $lines[] = 'Bloco ≤5 min/dia (após teto): '.self::formatSignedMinutesPt((int) $s['clt_result_minutes'])
                    .' · Fora de ±5 min/marcação: '.self::formatSignedMinutesPt((int) $s['outside_event_minutes']);
            }
            $lines[] = 'Regra CLT (detalhe): '.(string) ($clt['rule_applied'] ?? '');
            if (! empty($clt['snapshot_hint_pt'])) {
                $lines[] = (string) $clt['snapshot_hint_pt'];
            }
            if (isset($s['raw_diff_minutes'])) {
                $lines[] = 'Referência trabalhado − esperado: '.self::formatSignedMinutesPt((int) $s['raw_diff_minutes']);
            }
            $lines[] = 'Resultado no banco: '.self::formatSignedMinutesPt((int) ($s['extra_minutes_final'] ?? $this->extra_minutes));
        } elseif ($path === 'holiday_or_sunday_full' || $path === 'saturday_or_off_schedule_full') {
            $lines[] = 'Regra: todo o tempo trabalhado integra o saldo do dia (sem tolerância diária neste cenário).';
            $lines[] = 'Resultado no banco: '.self::formatSignedMinutesPt((int) ($s['extra_minutes_final'] ?? $this->extra_minutes));
        } elseif ($path === 'open_day') {
            $lines[] = 'Dia em aberto — snapshot das regras vigentes ao último recálculo.';
            if (isset($s['raw_diff_minutes'])) {
                $lines[] = 'Desvio trabalhado − esperado (referência): '.self::formatSignedMinutesPt((int) $s['raw_diff_minutes']);
            }
        }

        if (isset($s['calculation_confidence'])) {
            $lines[] = 'Confiança do cálculo (heurística): '.(string) $s['calculation_confidence'];
        }

        $src = (string) ($s['source_label_pt'] ?? '');
        if ($src !== '') {
            $lines[] = 'Origem da regra: '.$src;
        }

        return $lines;
    }

    public function toleranceSnapshotSummaryOneLinePt(): string
    {
        return implode(' · ', $this->toleranceImpactLinesPt());
    }

    /**
     * Texto curto para cartão de ponto / PDF (hover na data): só saldo final após tolerância.
     * Detalhe de auditoria permanece em `toleranceImpactLinesPt()` e no snapshot JSON.
     */
    public function toleranceCartaoHintPt(): string
    {
        $s = $this->tolerance_snapshot;
        if (! is_array($s) || $s === []) {
            return '';
        }

        $final = self::formatSignedMinutesPt((int) ($s['extra_minutes_final'] ?? $this->extra_minutes));

        return 'Saldo no banco (após tolerância): '.$final;
    }

    /** Snapshot com schema mínimo; `version` é coercível a inteiro (ex.: JSON como string) para comparações futuras v2+. */
    public function hasValidToleranceSnapshot(): bool
    {
        $s = $this->tolerance_snapshot;
        if (! is_array($s) || $s === []) {
            return false;
        }

        if (! array_key_exists('version', $s) || ! isset($s['engine'], $s['mode'])) {
            return false;
        }

        $version = (int) data_get($s, 'version', 0);

        return $version >= 1
            && is_string($s['engine'])
            && $s['engine'] !== ''
            && is_string($s['mode'])
            && $s['mode'] !== '';
    }

    /**
     * Badge de UX para cenários com tolerância em dia útil (a partir do snapshot).
     *
     * @return array{key:string, emoji:string, label:string, bg:string, color:string}|null
     */
    public function toleranceUxBadgePt(): ?array
    {
        $s = $this->tolerance_snapshot;
        if (! is_array($s) || $s === []) {
            return null;
        }
        if (($s['calculation_path'] ?? '') !== 'weekday_tolerance') {
            return null;
        }

        $raw = isset($s['raw_diff_minutes']) ? (int) $s['raw_diff_minutes'] : 0;
        $tol = max(0, (int) ($s['minutes'] ?? 0));
        $mode = (string) ($s['mode'] ?? '');
        if ($mode === '' && str_contains((string) ($s['mode_label_pt'] ?? ''), 'Desconto')) {
            $mode = WorkToleranceResolver::MODE_DAILY_DISCOUNT;
        }
        $absRaw = abs($raw);

        if ($absRaw <= $tol) {
            return [
                'key' => 'within',
                'emoji' => '🟢',
                'label' => 'Dentro da tolerância',
                'bg' => '#dcfce7',
                'color' => '#166534',
            ];
        }
        if ($mode === WorkToleranceResolver::MODE_DAILY_DISCOUNT) {
            return [
                'key' => 'applied_discount',
                'emoji' => '🔵',
                'label' => 'Tolerância aplicada',
                'bg' => '#dbeafe',
                'color' => '#1e40af',
            ];
        }

        return [
            'key' => 'outside_dead_band',
            'emoji' => '🟠',
            'label' => 'Fora da tolerância',
            'bg' => '#ffedd5',
            'color' => '#9a3412',
        ];
    }

    public function toleranceUxBadgeKey(): ?string
    {
        $badge = $this->toleranceUxBadgePt();

        return $badge['key'] ?? null;
    }

    /**
     * Enum estável para contrato mobile (`tt_kind`): within | applied_discount | outside_dead_band | none.
     *
     * @return self::TT_KIND_WITHIN|self::TT_KIND_APPLIED_DISCOUNT|self::TT_KIND_OUTSIDE_DEAD_BAND|self::TT_KIND_NONE
     */
    public function toleranceUxKind(): string
    {
        return $this->toleranceUxBadgeKey() ?? self::TT_KIND_NONE;
    }

    public function toleranceBalanceDiffersFromSnapshot(): bool
    {
        $s = $this->tolerance_snapshot;
        if (! is_array($s) || ($s['calculation_path'] ?? '') !== 'weekday_tolerance') {
            return false;
        }
        if (! array_key_exists('extra_minutes_final', $s)) {
            return false;
        }

        return (int) $this->extra_minutes !== (int) $s['extra_minutes_final'];
    }

    public function tolerancePostCloseMismatchPt(): ?string
    {
        if (! $this->is_closed || ! $this->toleranceBalanceDiffersFromSnapshot()) {
            return null;
        }

        return 'Saldo após fecho difere do resultado esperado pelo snapshot de tolerância.';
    }

    /**
     * @param  iterable<int, WorkDay|mixed>  $workDays
     * @return array{summary: array<string, float|int>, meta: array<string, int>}
     */
    public static function aggregateToleranceUxSummary(iterable $workDays, bool $auditableOnly = false): array
    {
        $counts = [
            'within' => 0,
            'applied_discount' => 0,
            'outside_dead_band' => 0,
        ];
        $validWorkDayRows = 0;
        $rowsWithoutSnapshot = 0;
        $ignoredLegacyDays = 0;
        $nonClassifiedDays = 0;
        $excludedByAuditableOnly = 0;
        $sumAbsRawDeviation = 0;
        $sumSignedRawDeviation = 0;

        foreach ($workDays as $wd) {
            if (! $wd instanceof self) {
                continue;
            }

            $validWorkDayRows++;

            $snap = $wd->tolerance_snapshot;
            if (! is_array($snap) || $snap === []) {
                $rowsWithoutSnapshot++;

                continue;
            }
            if (! $wd->hasValidToleranceSnapshot()) {
                $ignoredLegacyDays++;

                continue;
            }
            if ($auditableOnly && ! $wd->is_closed) {
                $excludedByAuditableOnly++;

                continue;
            }
            $key = $wd->toleranceUxBadgeKey();
            if ($key === null || ! isset($counts[$key])) {
                if ($key === null) {
                    $nonClassifiedDays++;
                }

                continue;
            }

            $counts[$key]++;
            $raw = (int) ($wd->tolerance_snapshot['raw_diff_minutes'] ?? 0);
            $sumAbsRawDeviation += abs($raw);
            $sumSignedRawDeviation += $raw;
        }

        $total = $counts['within'] + $counts['applied_discount'] + $counts['outside_dead_band'];

        if ($total === 0) {
            return [
                'summary' => [
                    'within' => $counts['within'],
                    'applied_discount' => $counts['applied_discount'],
                    'outside_dead_band' => $counts['outside_dead_band'],
                    'total_days' => 0,
                    'pct_within' => 0.0,
                    'pct_applied_discount' => 0.0,
                    'pct_outside_dead_band' => 0.0,
                    'avg_abs_deviation_minutes' => 0.0,
                    'avg_signed_deviation_minutes' => 0.0,
                ],
                'meta' => [
                    'rows_without_snapshot' => $rowsWithoutSnapshot,
                    'ignored_legacy_days' => $ignoredLegacyDays,
                    'excluded_open_days' => $excludedByAuditableOnly,
                    'excluded_by_auditable_only' => $excludedByAuditableOnly,
                    'non_classified_days' => $nonClassifiedDays,
                    'considered_days' => $total,
                    'valid_work_day_rows' => $validWorkDayRows,
                ],
            ];
        }

        $pctWithin = round(100 * $counts['within'] / $total, 1);
        $pctAppliedDiscount = round(100 * $counts['applied_discount'] / $total, 1);
        $pctOutsideDeadBand = round(100 * $counts['outside_dead_band'] / $total, 1);
        $pctDrift = round(100 - ($pctWithin + $pctAppliedDiscount + $pctOutsideDeadBand), 1);
        if ($pctDrift !== 0.0) {
            if ($counts['within'] > 0) {
                $pctWithin += $pctDrift;
            } elseif ($counts['applied_discount'] > 0) {
                $pctAppliedDiscount += $pctDrift;
            } elseif ($counts['outside_dead_band'] > 0) {
                $pctOutsideDeadBand += $pctDrift;
            }
        }

        return [
            'summary' => [
                'within' => $counts['within'],
                'applied_discount' => $counts['applied_discount'],
                'outside_dead_band' => $counts['outside_dead_band'],
                'total_days' => $total,
                'pct_within' => $pctWithin,
                'pct_applied_discount' => $pctAppliedDiscount,
                'pct_outside_dead_band' => $pctOutsideDeadBand,
                'avg_abs_deviation_minutes' => round($sumAbsRawDeviation / $total, 1),
                'avg_signed_deviation_minutes' => round($sumSignedRawDeviation / $total, 1),
            ],
            'meta' => [
                'rows_without_snapshot' => $rowsWithoutSnapshot,
                'ignored_legacy_days' => $ignoredLegacyDays,
                'excluded_open_days' => $excludedByAuditableOnly,
                'excluded_by_auditable_only' => $excludedByAuditableOnly,
                'non_classified_days' => $nonClassifiedDays,
                'considered_days' => $total,
                'valid_work_day_rows' => $validWorkDayRows,
            ],
        ];
    }
}
