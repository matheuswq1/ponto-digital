<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Delega ao mesmo modelo que {@see WorkDayService}: bucket progressivo + almoço por duração (efeito jornada).
 *
 * @see WorkDayService::calculate()
 */
final class CltEventToleranceCalculator
{
    public const EVENT_TOLERANCE_MINUTES = CltToleranceEngine::EVENT_TOLERANCE_MINUTES;

    public const DAILY_CAP_MINUTES = CltToleranceEngine::DAILY_CAP_MINUTES;

    public function __construct(
        private readonly CltToleranceEngine $cltToleranceEngine,
    ) {}

    /**
     * @param  list<array{type: string, time: string}>  $template
     * @param  list<Carbon>  $actualTimes
     * @return array{bank_minutes: int, clt: array<string, mixed>}
     */
    public function compute(string $calendarDate, string $timezone, array $template, array $actualTimes): array
    {
        if (count($template) !== count($actualTimes)) {
            throw new \InvalidArgumentException('Template e batidas têm tamanhos distintos.');
        }

        $slots = $this->buildSlotsAlignedWithWorkDayService(
            $calendarDate,
            $timezone,
            $template,
            $actualTimes,
        );

        $pack = $this->cltToleranceEngine->calculateProgressiveDailyCap($slots);
        $clt = $pack['clt'];

        if ($this->usesMergedLunchDuration($slots)) {
            $clt['engine_variant'] = 'progressive_daily_cap_duration_v1';
            $clt['calculation_engine_family'] = 'clt_event_progressive_duration';
            $clt['rule_applied'] = 'progressive_daily_cap_duration_v1';
        }

        return [
            'bank_minutes' => $pack['bank_minutes'],
            'clt' => $clt,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     */
    private function usesMergedLunchDuration(array $slots): bool
    {
        return count($slots) === 3
            && (($slots[1]['semantic_type'] ?? '') === 'lunch_duration');
    }

    /**
     * Replica {@see WorkDayService::buildCltSlotsForEngine} para dias com almoço: 4 batidas → 3 eventos,
     * delta do intervalo = configurado − duração real.
     *
     * @param  list<array{type: string, time: string}>  $template
     * @param  list<Carbon>  $actualTimes
     * @return list<array<string, mixed>>
     */
    private function buildSlotsAlignedWithWorkDayService(
        string $calendarDate,
        string $timezone,
        array $template,
        array $actualTimes,
    ): array {
        if (count($template) === 4 && count($actualTimes) === 4) {
            $exitTemplate = Carbon::parse(trim($calendarDate).' '.trim((string) $template[1]['time']), $timezone);
            $returnTemplate = Carbon::parse(trim($calendarDate).' '.trim((string) $template[2]['time']), $timezone);
            $configuredLunchMinutes = max(0, (int) round(($returnTemplate->timestamp - $exitTemplate->timestamp) / 60));
            if ($configuredLunchMinutes > 0) {
                $durationReal = max(0, (int) round(($actualTimes[2]->timestamp - $actualTimes[1]->timestamp) / 60));

                return [
                    [
                        'semantic_type' => 'entry',
                        'expected' => Carbon::parse(trim($calendarDate).' '.trim((string) $template[0]['time']), $timezone),
                        'actual' => $actualTimes[0],
                    ],
                    [
                        'semantic_type' => 'lunch_duration',
                        'expected' => $actualTimes[1]->copy()->addMinutes($configuredLunchMinutes),
                        'actual' => $actualTimes[2],
                        'delta_minutes_override' => $configuredLunchMinutes - $durationReal,
                    ],
                    [
                        'semantic_type' => 'final_out',
                        'expected' => Carbon::parse(trim($calendarDate).' '.trim((string) $template[3]['time']), $timezone),
                        'actual' => $actualTimes[3],
                    ],
                ];
            }
        }

        $semanticFour = ['entry', 'lunch_out', 'lunch_return', 'final_out'];
        $semanticTwo = ['entry', 'final_out'];
        $labels = count($template) === 4 ? $semanticFour : $semanticTwo;

        $slots = [];
        foreach ($template as $i => $slot) {
            $expected = Carbon::parse(trim($calendarDate).' '.trim((string) $slot['time']), $timezone);
            $slots[] = [
                'semantic_type' => $labels[$i] ?? (string) $slot['type'],
                'expected' => $expected,
                'actual' => $actualTimes[$i],
            ];
        }

        return $slots;
    }
}
