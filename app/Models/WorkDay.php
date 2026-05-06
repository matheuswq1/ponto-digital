<?php

namespace App\Models;

use App\Services\WorkToleranceResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDay extends Model
{
    use HasFactory;

    /** Versão do esquema do JSON em tolerance_snapshot (migrações futuras sem invalidar histórico). */
    public const TOLERANCE_SNAPSHOT_SCHEMA_VERSION = 1;

    /** Identificador do motor que gerou o snapshot (troca de algoritmo / comparação / rollback lógico). */
    public const TOLERANCE_ENGINE_ID = 'v1';

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
        } elseif ($path === 'holiday_or_sunday_full' || $path === 'saturday_or_off_schedule_full') {
            $lines[] = 'Regra: todo o tempo trabalhado integra o saldo do dia (sem tolerância diária neste cenário).';
            $lines[] = 'Resultado no banco: '.self::formatSignedMinutesPt((int) ($s['extra_minutes_final'] ?? $this->extra_minutes));
        } elseif ($path === 'open_day') {
            $lines[] = 'Dia em aberto — snapshot das regras vigentes ao último recálculo.';
            if (isset($s['raw_diff_minutes'])) {
                $lines[] = 'Desvio trabalhado − esperado (referência): '.self::formatSignedMinutesPt((int) $s['raw_diff_minutes']);
            }
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
