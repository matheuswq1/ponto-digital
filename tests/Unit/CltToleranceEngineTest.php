<?php

namespace Tests\Unit;

use App\Services\CltToleranceEngine;
use Carbon\Carbon;
use Tests\TestCase;

class CltToleranceEngineTest extends TestCase
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

    public function test_sum_within_bucket_six_zeros_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '10:01'),
            $this->slot('b', '11:00', '11:02'),
            $this->slot('c', '12:00', '12:03'),
        ]);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame(6, $r['clt_bucket_sum']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
    }

    public function test_five_plus_five_bucket_ten_zeros_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '10:05'),
            $this->slot('b', '11:00', '11:05'),
        ]);

        $this->assertSame(0, $r['bank_minutes']);
        $this->assertSame(10, $r['clt_bucket_sum']);
    }

    public function test_five_plus_five_plus_one_bucket_eleven_full_bank(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '10:05'),
            $this->slot('b', '11:00', '11:05'),
            $this->slot('c', '12:00', '12:01'),
        ]);

        $this->assertSame(11, $r['bank_minutes']);
        $this->assertSame(11, $r['clt_bucket_sum']);
        $this->assertSame(11, $r['clt_bucket_result']);
        $this->assertSame(0, $r['outside_event_sum']);
        $this->assertSame('exceeded_daily_cap_all_count', $r['clt']['rule_applied']);
    }

    public function test_single_event_six_minutes_outside(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('entry', '08:00', '08:06'),
        ]);

        $this->assertSame(6, $r['bank_minutes']);
        $this->assertSame(0, $r['clt_bucket_sum']);
        $this->assertSame(6, $r['outside_event_sum']);
    }

    public function test_mixed_outside_six_bucket_seven_bank_six(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '10:06'),
            $this->slot('b', '11:00', '11:04'),
            $this->slot('c', '12:00', '12:03'),
        ]);

        $this->assertSame(6, $r['outside_event_sum']);
        $this->assertSame(7, $r['clt_bucket_sum']);
        $this->assertSame(0, $r['clt_bucket_result']);
        $this->assertSame(6, $r['bank_minutes']);
    }

    public function test_mixed_exceeding_seventeen(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '10:06'),
            $this->slot('b', '11:00', '11:05'),
            $this->slot('c', '12:00', '12:05'),
            $this->slot('d', '13:00', '13:01'),
        ]);

        $this->assertSame(6, $r['outside_event_sum']);
        $this->assertSame(11, $r['clt_bucket_sum']);
        $this->assertSame(11, $r['clt_bucket_result']);
        $this->assertSame(17, $r['bank_minutes']);
    }

    /**
     * Cenário “estoura depois”: dois eventos no bucket (+5), último com |Δ|>5 vai só para outside — saldo = outside.
     */
    public function test_partial_bucket_then_last_event_outside_bank_equals_outside_only(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('entry', '08:00', '08:01'),
            $this->slot('lunch_return', '13:00', '13:04'),
            $this->slot('final_out', '17:00', '17:06'),
        ]);

        $this->assertSame(5, $r['clt_bucket_sum']);
        $this->assertSame(6, $r['outside_event_sum']);
        $this->assertSame(0, $r['clt_bucket_result']);
        $this->assertSame(6, $r['bank_minutes']);
        $this->assertSame('within_daily_cap', $r['clt']['rule_applied']);
    }

    /** Bucket só com variações dentro de ±5 e soma |−12|>10 → integra −12 inteiro (simétrico ao positivo). */
    public function test_delta_minutes_override_used_instead_of_timestamps(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            [
                'semantic_type' => 'lunch_duration',
                'expected' => Carbon::parse('2026-06-02 13:00:00', 'America/Sao_Paulo'),
                'actual' => Carbon::parse('2026-06-02 12:00:00', 'America/Sao_Paulo'),
                'delta_minutes_override' => 15,
            ],
        ]);

        $this->assertSame(15, $r['bank_minutes']);
        $this->assertSame('work_effect_duration', $r['clt']['events'][0]['delta_source']);
        $this->assertSame(15, $r['clt']['events'][0]['delta']);
    }

    public function test_negative_bucket_exceeds_daily_cap_counts_full_twelve(): void
    {
        $engine = new CltToleranceEngine;
        $r = $engine->calculate([
            $this->slot('a', '10:00', '09:57'),
            $this->slot('b', '11:00', '10:56'),
            $this->slot('c', '12:00', '11:55'),
        ]);

        $this->assertSame(-12, $r['clt_bucket_sum']);
        $this->assertSame(0, $r['outside_event_sum']);
        $this->assertSame(-12, $r['clt_bucket_result']);
        $this->assertSame(-12, $r['bank_minutes']);
        $this->assertSame('exceeded_daily_cap_all_count', $r['clt']['rule_applied']);
    }
}
