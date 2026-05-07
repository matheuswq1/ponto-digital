<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Adaptador legado: monta slots a partir do gabarito fixo e delega ao {@see CltToleranceEngine}.
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

        $pack = $this->cltToleranceEngine->calculate($slots);

        return [
            'bank_minutes' => $pack['bank_minutes'],
            'clt' => $pack['clt'],
        ];
    }
}
