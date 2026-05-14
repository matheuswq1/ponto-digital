<?php

namespace Tests\Unit;

use App\Services\CltToleranceEngine;
use Carbon\Carbon;
use Tests\TestCase;

class CltProgressiveCapEngineTest extends TestCase
{
    private const DATE = '2026-06-02';

    private const TZ = 'America/Sao_Paulo';

    private function slot(string $semantic, string $expectedHm, string $actualHm): array
    {
        return [
            'semantic_type' => $semantic,
            'expected' => Carbon::parse(self::DATE.' '.$expectedHm.':00', self::TZ),
            'actual' => Carbon::parse(self::DATE.' '.$actualHm.':00', self::TZ),
        ];
    }

    /** Override explícito do delta (ex.: efeito jornada no almoço). */
    private function slotDeltaOverride(string $semantic, int $deltaMinutes): array
    {
        $c = Carbon::parse(self::DATE.' 12:00:00', self::TZ);

        return [
            'semantic_type' => $semantic,
            'expected' => $c->copy(),
            'actual' => $c->copy(),
            'delta_minutes_override' => $deltaMinutes,
        ];
    }

    public function test_small_deviations_accumulate_until_bucket_abs_ge_ten_then_full_release(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('a', '10:00', '10:02'),
            $this->slot('b', '11:00', '11:02'),
            $this->slot('c', '12:00', '12:03'),
            $this->slot('d', '13:00', '13:04'),
        ]);

        $this->assertSame(11, $r['bank_minutes']);
        $this->assertSame(0, $r['clt_bucket_sum']);
        $this->assertSame(11, $r['clt_bucket_result']);
        $this->assertTrue($r['clt']['tolerance_closed_end']);
        $last = $r['events'][3];
        $this->assertSame('daily_cap_reached', $last['tolerance_close_reason']);
        $this->assertSame(11, $last['released_bucket_minutes']);
        $this->assertSame('daily_cap_release', $last['progressive_classification']);
        $timeline = $r['clt']['timeline'];
        $this->assertCount(4, $timeline);
        $this->assertSame(11, $timeline[3]['bank']);
        $this->assertSame(11, $timeline[3]['bucket_release']);
        $this->assertSame('clt_event_progressive_cap', $r['clt']['calculation_engine_family']);
    }

    public function test_two_plus_five_events_hit_exact_ten_bucket_flush(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('a', '10:00', '10:05'),
            $this->slot('b', '11:00', '11:05'),
        ]);

        $this->assertSame(10, $r['bank_minutes']);
        $this->assertSame(0, $r['clt_bucket_sum']);
        $this->assertSame(10, $r['clt_bucket_result']);
    }

    public function test_seven_minutes_splits_five_to_bucket_two_to_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('entry', '08:00', '08:07'),
        ]);

        $this->assertSame(-2, $r['bank_minutes']);
        $this->assertSame(-5, $r['clt_bucket_sum']);
        $this->assertSame(0, $r['clt_bucket_result']);
        $ev = $r['events'][0];
        $this->assertSame(-2, $ev['immediate_to_bank_minutes']);
        $this->assertSame('partial_immediate_bank', $ev['progressive_classification']);
    }

    /** Entrada 6 min antecipada: crédito +6 → +5 no bucket e +1 no saldo. */
    public function test_entry_six_minutes_early_splits_positive_bucket_and_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('entry', '08:00', '07:54'),
        ]);

        $this->assertSame(1, $r['bank_minutes']);
        $this->assertSame(5, $r['clt_bucket_sum']);
        $ev = $r['events'][0];
        $this->assertSame(1, $ev['immediate_to_bank_minutes']);
        $this->assertSame('partial_immediate_bank', $ev['progressive_classification']);
    }

    public function test_single_ten_minute_event_counts_integral_and_closes(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('entry', '08:00', '08:10'),
        ]);

        $this->assertSame(-10, $r['bank_minutes']);
        $this->assertTrue($r['clt']['tolerance_closed_end']);
        $ev = $r['events'][0];
        $this->assertSame('event_exceeds_daily_cap', $ev['tolerance_close_reason']);
        $this->assertSame(0, $ev['released_bucket_minutes']);
        $this->assertSame('event_exceeds_cap', $ev['progressive_classification']);
        $tl = $r['clt']['timeline'][0];
        $this->assertSame(-10, $tl['bank']);
        $this->assertSame(-10, $tl['bank_step_delta']);
        $this->assertSame(0, $tl['bucket']);
    }

    public function test_after_close_next_events_go_integral(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('a', '08:00', '08:10'),
            $this->slot('b', '12:00', '12:03'),
        ]);

        $this->assertSame(13, $r['bank_minutes']);
        $second = $r['events'][1];
        $this->assertSame('post_cap_direct_bank', $second['progressive_classification']);
    }

    public function test_negative_seven_symmetric(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('out', '17:00', '16:53'),
        ]);

        $this->assertSame(-2, $r['bank_minutes']);
        $this->assertSame(-5, $r['clt_bucket_sum']);
    }

    public function test_big_event_releases_prior_bucket(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('a', '08:00', '08:04'),
            $this->slot('b', '12:00', '12:15'),
        ]);

        $this->assertSame(19, $r['bank_minutes']);
        $ev = $r['events'][1];
        $this->assertSame(4, $ev['released_bucket_minutes']);
        $this->assertSame('event_exceeds_daily_cap', $ev['tolerance_close_reason']);
    }

    public function test_bucket_under_ten_stays_off_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('a', '10:00', '10:04'),
            $this->slot('b', '11:00', '11:04'),
        ]);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame(8, $r['clt_bucket_sum']);
        $this->assertFalse($r['clt']['tolerance_closed_end']);
    }

    /** Medidor diário (|delta| acumulado) ≥10 libera bucket mesmo quando |bucket| &lt;10 — saldo líquido pode ser 0. */
    public function test_daily_meter_releases_residual_bucket_net_zero_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculateProgressiveDailyCap([
            $this->slot('entry', '08:00', '08:06'),
            $this->slotDeltaOverride('lunch_effect', 2),
            $this->slot('exit', '18:00', '18:04'),
        ]);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame(0, $r['clt_bucket_sum']);
        $this->assertTrue($r['clt']['tolerance_closed_end']);
        $last = $r['events'][2];
        $this->assertSame('daily_tolerance_meter_reached', $last['tolerance_close_reason']);
        $this->assertSame(1, $last['released_bucket_minutes']);
        $this->assertSame(12, $last['daily_tolerance_meter_after_event']);
    }
}
