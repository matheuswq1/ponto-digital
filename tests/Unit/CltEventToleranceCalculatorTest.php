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
        $this->assertSame(9, $r['clt']['sum_within_event_tolerance']);
        $this->assertNotNull($r['clt']['snapshot_hint_pt']);
    }

    public function test_all_within_five_and_sum_over_ten_counts_full_sum(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:03', '13:04', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(11, $r['bank_minutes']);
        $this->assertSame('exceeded_daily_cap_all_count', $r['clt']['rule_applied']);
        $this->assertSame(11, $r['clt']['sum_within_event_tolerance']);
        $this->assertNull($r['clt']['snapshot_hint_pt']);
    }

    public function test_mixed_with_one_over_five_only_small_bucket_summed_and_outside_added(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['08:04', '12:06', '13:03', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(7, $r['clt']['sum_within_event_tolerance']);
        $this->assertSame(6, $r['clt']['outside_event_tolerance_sum']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
        $this->assertSame(6, $r['bank_minutes']);
        $this->assertNotNull($r['clt']['snapshot_hint_pt']);
    }

    public function test_negative_small_variations_exceed_cap_counts_full(): void
    {
        $calc = $this->app->make(CltEventToleranceCalculator::class);
        $times = $this->timesFromHm(['07:56', '11:57', '12:56', '17:00']);

        $r = $calc->compute(self::DATE, self::TZ, $this->templateExample(), $times);

        $this->assertSame(-11, $r['clt']['sum_within_event_tolerance']);
        $this->assertSame('exceeded_daily_cap_all_count', $r['clt']['rule_applied']);
        $this->assertSame(-11, $r['bank_minutes']);
    }
}
