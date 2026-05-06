<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Tolerância por evento (batida × horário previsto) com regra 5 min por marcação e teto 10 min no dia.
 *
 * - Variações com |delta| ≤ 5 entram na soma diária CLT.
 * - Variações com |delta| > 5 não entram nessa soma; integram sempre o saldo (`outside`).
 * - Se |soma CLT| ≤ 10 → contribuição do bloco CLT = 0.
 * - Se |soma CLT| > 10 → contribuição do bloco CLT = soma CLT integral.
 *
 * @see WorkDayService::calculate()
 */
final class CltEventToleranceCalculator
{
    public const EVENT_TOLERANCE_MINUTES = 5;

    public const DAILY_CAP_MINUTES = 10;

    /**
     * @param  list<array{type: string, time: string}>  $template  horários previstos (H:i ou H:i:s)
     * @param  list<Carbon>  $actualTimes  batidas reais na mesma ordem do template
     * @return array{bank_minutes: int, clt: array<string, mixed>}
     */
    public function compute(string $calendarDate, string $timezone, array $template, array $actualTimes): array
    {
        if (count($template) !== count($actualTimes)) {
            throw new \InvalidArgumentException('Template e batidas têm tamanhos distintos.');
        }

        $sumWithinEventTolerance = 0;
        $outsideSum = 0;
        $events = [];

        foreach ($template as $i => $slot) {
            $type = (string) $slot['type'];
            $timeStr = (string) $slot['time'];
            $expected = Carbon::parse(trim($calendarDate).' '.trim($timeStr), $timezone);

            $actual = $actualTimes[$i]->copy();
            $delta = (int) round(($actual->timestamp - $expected->timestamp) / 60);
            $abs = abs($delta);

            if ($abs <= self::EVENT_TOLERANCE_MINUTES) {
                $sumWithinEventTolerance += $delta;
                $events[] = [
                    'type' => $type,
                    'expected' => $expected->format('H:i'),
                    'actual' => $actual->format('H:i'),
                    'delta' => $delta,
                    'bucket' => 'within_event_tolerance',
                    'counted_in_clt_sum' => true,
                ];
            } else {
                $outsideSum += $delta;
                $events[] = [
                    'type' => $type,
                    'expected' => $expected->format('H:i'),
                    'actual' => $actual->format('H:i'),
                    'delta' => $delta,
                    'bucket' => 'outside_event_tolerance',
                    'counted_in_clt_sum' => false,
                ];
            }
        }

        if (abs($sumWithinEventTolerance) <= self::DAILY_CAP_MINUTES) {
            $cltMinutes = 0;
            $rule = 'within_daily_cap';
        } else {
            $cltMinutes = $sumWithinEventTolerance;
            $rule = 'exceeded_daily_cap_all_count';
        }

        $bankMinutes = $cltMinutes + $outsideSum;

        $snapshotHintPt = null;
        if ($rule === 'within_daily_cap' && $sumWithinEventTolerance !== 0) {
            $snapshotHintPt = 'A soma das variações por marcação (até '
                .self::EVENT_TOLERANCE_MINUTES.' min cada) ficou dentro do teto diário de '
                .self::DAILY_CAP_MINUTES.' min — esse bloco não altera o saldo. '
                .'Desvios acima de '
                .self::EVENT_TOLERANCE_MINUTES.' min por marcação continuam a contar integralmente.';
        }

        $eventModel = count($template) === 2 ? '2_events' : '4_events';

        return [
            'bank_minutes' => $bankMinutes,
            'clt' => [
                'event_model' => $eventModel,
                'event_tolerance_minutes' => self::EVENT_TOLERANCE_MINUTES,
                'daily_cap_minutes' => self::DAILY_CAP_MINUTES,
                'sum_within_event_tolerance' => $sumWithinEventTolerance,
                'outside_event_tolerance_sum' => $outsideSum,
                'result_minutes_from_clt_small_bucket' => $cltMinutes,
                'rule_applied' => $rule,
                'integration_strategy' => 'clt_events_bank_primary',
                'integration_note_pt' => 'Base de cálculo do banco: eventos de ponto pareados × previsto (modo CLT). '
                    .'raw_diff_minutes é trabalhado − esperado e pode diferir do resultado por evento.',
                'snapshot_hint_pt' => $snapshotHintPt,
                'events' => $events,
            ],
        ];
    }
}
