<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\WorkEventDeviation;
use Carbon\Carbon;

/**
 * Motor CLT por evento: ±5 min por marcação, teto 10 min no dia no bucket;
 * soma do bucket inteira no saldo se ultrapassar o teto; marcas >5 min vão direto ao saldo.
 *
 * @see WorkDayService::calculate()
 */
final class CltToleranceEngine
{
    public const EVENT_TOLERANCE_MINUTES = 5;

    public const DAILY_CAP_MINUTES = 10;

    /**
     * @param  list<array{semantic_type: string, expected: Carbon, actual: Carbon}>  $slots
     * @return array{bank_minutes: int, clt_bucket_sum: int, clt_bucket_result: int, outside_event_sum: int, event_tolerance_minutes: int, daily_cap_minutes: int, events: list<array<string, mixed>>, clt: array<string, mixed>}
     */
    public function calculate(array $slots): array
    {
        if ($slots === []) {
            throw new \InvalidArgumentException('Lista de eventos CLT vazia.');
        }

        $cltBucketSum = 0;
        $outsideSum = 0;
        $snapshotEvents = [];
        $detailedEvents = [];

        foreach ($slots as $slot) {
            $expected = $slot['expected'];
            $actual = $slot['actual'];
            $semantic = (string) $slot['semantic_type'];

            $delta = (int) round(($actual->timestamp - $expected->timestamp) / 60);
            $abs = abs($delta);
            $within = $abs <= self::EVENT_TOLERANCE_MINUTES;

            if ($within) {
                $cltBucketSum += $delta;
            } else {
                $outsideSum += $delta;
            }

            $dto = new WorkEventDeviation(
                type: $semantic,
                expectedAt: $expected->copy(),
                actualAt: $actual->copy(),
                diffMinutes: $delta,
                withinEventTolerance: $within,
                enteredCltBucket: $within,
                outsideEventTolerance: ! $within,
            );

            $snapshotEvents[] = $dto->toSnapshotEventArray();
            $detailedEvents[] = [
                'type' => $semantic,
                'expected' => $expected->format('H:i'),
                'actual' => $actual->format('H:i'),
                'delta' => $delta,
                'bucket' => $within ? 'within_event_tolerance' : 'outside_event_tolerance',
                'counted_in_clt_sum' => $within,
            ];
        }

        if (abs($cltBucketSum) <= self::DAILY_CAP_MINUTES) {
            $cltBucketResult = 0;
            $rule = 'within_daily_cap';
        } else {
            $cltBucketResult = $cltBucketSum;
            $rule = 'exceeded_daily_cap_all_count';
        }

        $bankMinutes = $cltBucketResult + $outsideSum;

        $snapshotHintPt = null;
        if ($rule === 'within_daily_cap' && $cltBucketSum !== 0) {
            $snapshotHintPt = 'A soma das variações por marcação (até '
                .self::EVENT_TOLERANCE_MINUTES.' min cada) ficou dentro do teto diário de '
                .self::DAILY_CAP_MINUTES.' min — esse bloco não altera o saldo. '
                .'Desvios acima de '
                .self::EVENT_TOLERANCE_MINUTES.' min por marcação continuam a contar integralmente.';
        }

        $eventModel = count($slots) === 2 ? '2_events' : '4_events';

        $cltNested = [
            'event_model' => $eventModel,
            'event_tolerance_minutes' => self::EVENT_TOLERANCE_MINUTES,
            'daily_cap_minutes' => self::DAILY_CAP_MINUTES,
            'sum_within_event_tolerance' => $cltBucketSum,
            'outside_event_tolerance_sum' => $outsideSum,
            'result_minutes_from_clt_small_bucket' => $cltBucketResult,
            'rule_applied' => $rule,
            'integration_strategy' => 'clt_events_bank_primary',
            'integration_note_pt' => 'Base de cálculo do banco: eventos de ponto pareados × previsto (motor CLT). '
                .'raw_diff_minutes é trabalhado − esperado e pode diferir do resultado por evento.',
            'snapshot_hint_pt' => $snapshotHintPt,
            'events' => $detailedEvents,
            'events_audit' => $snapshotEvents,
        ];

        return [
            'bank_minutes' => $bankMinutes,
            'clt_bucket_sum' => $cltBucketSum,
            'clt_bucket_result' => $cltBucketResult,
            'outside_event_sum' => $outsideSum,
            'event_tolerance_minutes' => self::EVENT_TOLERANCE_MINUTES,
            'daily_cap_minutes' => self::DAILY_CAP_MINUTES,
            'events' => $snapshotEvents,
            'clt' => $cltNested,
        ];
    }
}
