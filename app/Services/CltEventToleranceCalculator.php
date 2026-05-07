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

        $slots = $this->buildSlotsAlignedWithWorkDayService(
            $calendarDate,
            $timezone,
            $template,
            $actualTimes,
        );

        $pack = $this->cltToleranceEngine->calculate($slots);

        return [
            'bank_minutes' => $pack['bank_minutes'],
            'clt' => $pack['clt'],
        ];
    }

    /**
     * Replica a mesma ideia de {@see WorkDayService::buildCltSlotsForEngine} em modo não strict:
     * 4 batidas com intervalo configurado > 0 → 3 eventos (entrada, duração do almoço, saída final).
     *
     * Minutos do intervalo são inferidos do gabarito (horário previsto de retorno − saída para almoço).
     *
     * @param  list<array{type: string, time: string}>  $template
     * @param  list<Carbon>  $actualTimes
     * @return list<array{semantic_type: string, expected: Carbon, actual: Carbon}>
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
