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

    public function test_all_within_five_and_bucket_under_cap_yields_zero_bank(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:03', '13:02', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('3_events_lunch_duration', $r['clt']['event_model']);
        $this->assertSame('clt_event_progressive_duration', $r['clt']['calculation_engine_family']);
        $this->assertSame('progressive_daily_cap_duration_v1', $r['clt']['rule_applied']);
    }

    /** Entrada +4 e almoço 61 min (config 60) ⇒ delta efeito −1 no bucket progressivo; saldo 0. */
    public function test_work_effect_lunch_longer_than_config_small_net_bucket(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:03', '13:04', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('progressive_daily_cap_duration_v1', $r['clt']['rule_applied']);
    }

    /** Saída para almoço “tarde” não gera evento à parte — só entra na duração real vs configurada. */
    public function test_lunch_exit_clock_irrelevant_only_duration_matters(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:06', '13:03', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('clt_event_progressive_duration', $r['clt']['calculation_engine_family']);
    }

    /** Somas pequenas no bucket residual não ultrapassam o mecanismo progressivo → saldo 0 neste cenário. */
    public function test_negative_small_events_under_progressive_cap_zeros_bank(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['07:56', '11:57', '12:56', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame('3_events_lunch_duration', $r['clt']['event_model']);
    }
}
