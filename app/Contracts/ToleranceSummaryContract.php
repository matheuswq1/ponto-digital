<?php

namespace App\Contracts;

use App\Http\Controllers\Api\ReportController;

/**
 * Contrato estável do endpoint {@see ReportController::toleranceSummary}
 * para ordem e chaves de `meta.reconciliation`.
 *
 * Manter alinhado com `docs/api/tolerance-summary-openapi.yaml`.
 */
final class ToleranceSummaryContract
{
    /**
     * Ordem intencional das chaves em {@code meta.reconciliation} (JSON PHP preserva ordem de inserção).
     *
     * @var list<string>
     */
    public const RECONCILIATION_KEYS = [
        'total_rows_in_period',
        'valid_work_day_rows',
        'rows_without_snapshot',
        'ignored_legacy_days',
        'excluded_open_days',
        'non_classified_days',
        'considered_days',
        'excluded_by_auditable_only',
        'sum_of_buckets',
        'identity_holds',
    ];

    /**
     * @param  array<string, int|float|bool>  $values  uma entrada por chave em {@see self::RECONCILIATION_KEYS}
     * @return array<string, int|float|bool>
     */
    public static function orderedReconciliation(array $values): array
    {
        $ordered = [];
        foreach (self::RECONCILIATION_KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                throw new \InvalidArgumentException("Missing reconciliation value for key [{$key}].");
            }
            $ordered[$key] = $values[$key];
        }

        return $ordered;
    }
}
