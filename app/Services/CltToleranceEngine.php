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
     * @param  list<array{semantic_type: string, expected: Carbon, actual: Carbon, delta_minutes_override?: int|null}>  $slots
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

            $delta = $this->slotSignedDeltaMinutes($slot);
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
                'delta_source' => $this->deltaSourceLabel($slot),
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

        $eventModel = $this->resolveEventModel($slots);

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

    /**
     * Motor CLT “bucket progressivo”: até 5 min → bucket; 6–9 → ±5 no bucket + resto no saldo;
     * ≥10 min ou |bucket|≥10 após o evento → todo o bucket vai para o saldo e a tolerância do dia encerra;
     * depois disso cada evento integral no saldo.
     *
     * Snapshot `clt`: inclui `timeline` (passo a passo bucket/banco) e `calculation_engine_family` para analytics.
     *
     * @param  list<array{semantic_type: string, expected: Carbon, actual: Carbon, delta_minutes_override?: int|null}>  $slots
     * @return array{bank_minutes: int, clt_bucket_sum: int, clt_bucket_result: int, outside_event_sum: int, event_tolerance_minutes: int, daily_cap_minutes: int, events: list<array<string, mixed>>, clt: array<string, mixed>}
     */
    public function calculateProgressiveDailyCap(array $slots): array
    {
        if ($slots === []) {
            throw new \InvalidArgumentException('Lista de eventos CLT vazia.');
        }

        $bucket = 0;
        $bank = 0;
        $toleranceClosed = false;

        $releasedBucketsSigned = 0;
        $immediateCarveSigned = 0;
        $integralBigSigned = 0;
        $postCloseSigned = 0;

        $snapshotEvents = [];
        $detailedEvents = [];
        $timeline = [];

        foreach ($slots as $slot) {
            $bankBeforeStep = $bank;
            $expected = $slot['expected'];
            $actual = $slot['actual'];
            $semantic = (string) $slot['semantic_type'];
            $delta = $this->slotSignedDeltaMinutes($slot);
            $abs = abs($delta);

            $toleranceClosedBeforeEvent = $toleranceClosed;
            $bucketBeforeEvent = $bucket;
            $releasedBucketMinutes = null;
            $immediateToBank = null;
            $toleranceCloseReason = null;

            if ($toleranceClosed) {
                $bank += $delta;
                $postCloseSigned += $delta;

                $dto = new WorkEventDeviation(
                    type: $semantic,
                    expectedAt: $expected->copy(),
                    actualAt: $actual->copy(),
                    diffMinutes: $delta,
                    withinEventTolerance: false,
                    enteredCltBucket: false,
                    outsideEventTolerance: true,
                );

                $snapProgressive = [
                    'bucket_before_event' => $bucketBeforeEvent,
                    'bucket_after_before_cap_check' => $bucket,
                    'bucket_after_event' => $bucket,
                    'tolerance_closed' => true,
                    'tolerance_close_reason' => null,
                    'released_bucket_minutes' => null,
                    'immediate_to_bank_minutes' => null,
                    'progressive_classification' => 'post_cap_direct_bank',
                ];
                $snapshotEvents[] = array_merge($dto->toSnapshotEventArray(), $snapProgressive);

                $detailedEvents[] = [
                    'type' => $semantic,
                    'expected' => $expected->format('H:i'),
                    'actual' => $actual->format('H:i'),
                    'delta' => $delta,
                    'delta_source' => $this->deltaSourceLabel($slot),
                    ...$snapProgressive,
                    'tolerance_closed_before_event' => true,
                ];

                $timeline[] = [
                    'event' => $semantic,
                    'delta' => $delta,
                    'bucket' => $bucket,
                    'bank' => $bank,
                    'bank_step_delta' => $bank - $bankBeforeStep,
                    'bucket_release' => null,
                    'immediate_to_bank' => null,
                    'tolerance_closed_after' => true,
                    'progressive_classification' => 'post_cap_direct_bank',
                ];

                continue;
            }

            $enteredBucketPortion = false;
            $outsideMicro = false;

            if ($abs <= self::EVENT_TOLERANCE_MINUTES) {
                $bucket += $delta;
                $enteredBucketPortion = true;
            } elseif ($abs < self::DAILY_CAP_MINUTES) {
                $stepBucket = $delta > 0 ? self::EVENT_TOLERANCE_MINUTES : -self::EVENT_TOLERANCE_MINUTES;
                $bucket += $stepBucket;
                $immediateToBank = $delta > 0
                    ? $delta - self::EVENT_TOLERANCE_MINUTES
                    : $delta + self::EVENT_TOLERANCE_MINUTES;
                $bank += $immediateToBank;
                $immediateCarveSigned += $immediateToBank;
                $enteredBucketPortion = true;
                $outsideMicro = true;
            } else {
                $releasedBucketMinutes = $bucket;
                $bank += $delta + $bucket;
                $releasedBucketsSigned += $bucket;
                $integralBigSigned += $delta;
                $bucket = 0;
                $toleranceClosed = true;
                $toleranceCloseReason = 'event_exceeds_daily_cap';
                $outsideMicro = true;
            }

            $bucketAfterBeforeCapCheck = $bucket;

            if (! $toleranceClosed && abs($bucket) >= self::DAILY_CAP_MINUTES) {
                $releasedBucketMinutes = $bucket;
                $bank += $bucket;
                $releasedBucketsSigned += $bucket;
                $bucket = 0;
                $toleranceClosed = true;
                $toleranceCloseReason = $toleranceCloseReason ?? 'daily_cap_reached';
            }

            $bucketAfterEvent = $bucket;

            $dto = new WorkEventDeviation(
                type: $semantic,
                expectedAt: $expected->copy(),
                actualAt: $actual->copy(),
                diffMinutes: $delta,
                withinEventTolerance: $abs <= self::EVENT_TOLERANCE_MINUTES,
                enteredCltBucket: $enteredBucketPortion,
                outsideEventTolerance: $outsideMicro || $abs > self::EVENT_TOLERANCE_MINUTES,
            );

            $toleranceCloseReasonForSnapshot = (! $toleranceClosedBeforeEvent && $toleranceClosed)
                ? $toleranceCloseReason
                : null;

            $classification = match (true) {
                $toleranceCloseReasonForSnapshot === 'daily_cap_reached' => 'daily_cap_release',
                $toleranceCloseReasonForSnapshot === 'event_exceeds_daily_cap' => 'event_exceeds_cap',
                $abs <= self::EVENT_TOLERANCE_MINUTES => 'within_event_tolerance',
                $abs < self::DAILY_CAP_MINUTES => 'partial_immediate_bank',
                default => 'event_exceeds_cap',
            };

            $snapProgressive = [
                'bucket_before_event' => $bucketBeforeEvent,
                'bucket_after_before_cap_check' => $bucketAfterBeforeCapCheck,
                'bucket_after_event' => $bucketAfterEvent,
                'tolerance_closed' => $toleranceClosed,
                'tolerance_close_reason' => $toleranceCloseReasonForSnapshot,
                'released_bucket_minutes' => $releasedBucketMinutes,
                'immediate_to_bank_minutes' => $immediateToBank,
                'progressive_classification' => $classification,
            ];
            $snapshotEvents[] = array_merge($dto->toSnapshotEventArray(), $snapProgressive);

            $detailedEvents[] = [
                'type' => $semantic,
                'expected' => $expected->format('H:i'),
                'actual' => $actual->format('H:i'),
                'delta' => $delta,
                'delta_source' => $this->deltaSourceLabel($slot),
                ...$snapProgressive,
                'tolerance_closed_before_event' => false,
            ];

            $timeline[] = [
                'event' => $semantic,
                'delta' => $delta,
                'bucket' => $bucketAfterEvent,
                'bank' => $bank,
                'bank_step_delta' => $bank - $bankBeforeStep,
                'bucket_release' => $releasedBucketMinutes,
                'immediate_to_bank' => $immediateToBank,
                'tolerance_closed_after' => $toleranceClosed,
                'progressive_classification' => $classification,
            ];
        }

        $outsidePortionSigned = $immediateCarveSigned + $integralBigSigned + $postCloseSigned;
        $bucketFinalResidual = $bucket;

        $eventModel = $this->resolveEventModel($slots);

        $cltNested = [
            'engine_variant' => 'progressive_daily_cap_v1',
            'event_model' => $eventModel,
            'event_tolerance_minutes' => self::EVENT_TOLERANCE_MINUTES,
            'daily_cap_minutes' => self::DAILY_CAP_MINUTES,
            'sum_within_event_tolerance' => $bucketFinalResidual,
            'outside_event_tolerance_sum' => $outsidePortionSigned,
            'result_minutes_from_clt_small_bucket' => $releasedBucketsSigned,
            'integration_strategy' => 'clt_progressive_cap_primary',
            'integration_note_pt' => 'Bucket progressivo: até 5 min só no bucket; 6–9 min divide ±5 no bucket e resto no saldo; '
                .'≥10 min ou |bucket|≥10 libera todo o bucket no saldo e encerra a tolerância do dia; depois todos os eventos são integralmente no saldo.',
            'tolerance_closed_end' => $toleranceClosed,
            'timeline' => $timeline,
            'calculation_engine_family' => 'clt_event_progressive_cap',
            'events_progressive' => $detailedEvents,
            'events' => $detailedEvents,
            'events_audit' => $snapshotEvents,
            'rule_applied' => 'progressive_daily_cap_v1',
        ];

        return [
            'bank_minutes' => $bank,
            'clt_bucket_sum' => $bucketFinalResidual,
            'clt_bucket_result' => $releasedBucketsSigned,
            'outside_event_sum' => $outsidePortionSigned,
            'event_tolerance_minutes' => self::EVENT_TOLERANCE_MINUTES,
            'daily_cap_minutes' => self::DAILY_CAP_MINUTES,
            'events' => $snapshotEvents,
            'clt' => $cltNested,
        ];
    }

    /**
     * @param  list<array{semantic_type: string, expected: Carbon, actual: Carbon}>  $slots
     */
    private function resolveEventModel(array $slots): string
    {
        return match (count($slots)) {
            2 => '2_events',
            3 => $this->hasLunchDurationSemantic($slots) ? '3_events_lunch_duration' : '3_events',
            default => '4_events',
        };
    }

    /**
     * @param  list<array{semantic_type: string, expected: Carbon, actual: Carbon}>  $slots
     */
    private function hasLunchDurationSemantic(array $slots): bool
    {
        foreach ($slots as $slot) {
            if (($slot['semantic_type'] ?? '') === 'lunch_duration') {
                return true;
            }
        }

        return false;
    }

    /**
     * Minutos assinados por evento: por defeito {@see Carbon} `actual − expected`.
     * Na **entrada** (`semantic_type === entry`) o sinal é invertido: positivo = chegada antecipada (crédito),
     * negativo = atraso — alinhado ao efeito no saldo que RH espera.
     * Com `delta_minutes_override`, o chamador define o sinal (ex.: efeito jornada no almoço).
     *
     * @param  array{semantic_type?: string, expected: Carbon, actual: Carbon, delta_minutes_override?: int|null}  $slot
     */
    private function slotSignedDeltaMinutes(array $slot): int
    {
        if (array_key_exists('delta_minutes_override', $slot) && $slot['delta_minutes_override'] !== null) {
            return (int) $slot['delta_minutes_override'];
        }

        $expected = $slot['expected'];
        $actual = $slot['actual'];

        $base = (int) round(($actual->timestamp - $expected->timestamp) / 60);

        if (($slot['semantic_type'] ?? '') === 'entry') {
            return -$base;
        }

        return $base;
    }

    /**
     * @param  array{delta_minutes_override?: int|null}  $slot
     */
    private function deltaSourceLabel(array $slot): string
    {
        return array_key_exists('delta_minutes_override', $slot) && $slot['delta_minutes_override'] !== null
            ? 'work_effect_duration'
            : 'clock';
    }
}
