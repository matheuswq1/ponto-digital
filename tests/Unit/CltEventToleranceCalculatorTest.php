<?php

namespace Tests\Unit;

use App\Services\CltEventToleranceCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class CltEventToleranceCalculatorTest extends TestCase
{
    private const DATE = '2026-06-02';

    private const TZ = 'America/Sao_Paulo';

    /** Gabarito 08 / 12 / 13 / 17 como nos testes de WorkSchedule Department */
    private function templateExample(): array
    {
        return [
            ['type' => 'entrada', 'time' => '08:00'],
            ['type' => 'saida', 'time' => '12:00'],
            ['type' => 'entrada', 'time' => '13:00'],
            ['type' => 'saida', 'time' => '17:00'],
        ];
    }

    private function timesFromHm(array $timesHm): array
    {
        $out = [];
        foreach ($timesHm as $hm) {
            $out[] = Carbon::parse(self::DATE.' '.$hm.':00', self::TZ);
        }

        return $out;
    }

    public function test_all_within_five_and_sum_at_most_ten_yields_zero(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:03', '13:02', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
        $this->assertSame(3, $r['clt']['sum_within_event_tolerance']);
        $this->assertSame('3_events_lunch_duration', $r['clt']['event_model']);
        $this->assertNotNull($r['clt']['snapshot_hint_pt']);
    }

    /** Com intervalo como duração: soma no bucket +5 → dentro do teto diário 10 → banco 0. */
    public function test_all_within_five_and_bucket_sum_five_stays_within_daily_cap(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:03', '13:04', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
        $this->assertSame(5, $r['clt']['sum_within_event_tolerance']);
        $this->assertNotNull($r['clt']['snapshot_hint_pt']);
    }

    /** Saída para almoço +6 min não existe mais como evento isolado; intervalo como duração deixa tudo no bucket +1 → banco 0. */
    public function test_lunch_exit_deviation_absorbed_into_duration_event_bucket_only(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:06', '13:03', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(1, $r['clt']['sum_within_event_tolerance']);
        $this->assertSame(0, $r['clt']['outside_event_tolerance_sum']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
        $this->assertSame(0, $r['bank_minutes']);
        $this->assertNotNull($r['clt']['snapshot_hint_pt']);
    }

    /** Somas negativas pequenas no bucket −5 ficam dentro do teto diário → banco 0. */
    public function test_negative_small_bucket_within_daily_cap_zeros_bank(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['07:56', '11:57', '12:56', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(-5, $r['clt']['sum_within_event_tolerance']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
        $this->assertSame(0, $r['bank_minutes']);
    }
}
