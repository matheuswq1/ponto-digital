<?php

namespace Tests\Unit;

use App\Models\WorkDay;
use App\Services\WorkToleranceResolver;
use Tests\TestCase;

class WorkDayToleranceUxTest extends TestCase
{
    public function test_tolerance_impact_lines_weekday_discount(): void
    {
        $wd = WorkDay::make([
            'extra_minutes' => 5,
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 15,
                'minutes' => 10,
                'mode_label_pt' => 'Desconto no saldo diário',
                'extra_minutes_final' => 5,
                'source_label_pt' => 'empresa',
            ],
        ]);

        $lines = $wd->toleranceImpactLinesPt();

        $this->assertStringContainsString('Saldo antes da tolerância', $lines[0]);
        $this->assertStringContainsString('Tolerância aplicada', $lines[1]);
        $this->assertStringContainsString('Resultado no banco', $lines[2]);
        $this->assertStringContainsString('Origem da regra', $lines[3]);
    }

    public function test_format_signed_minutes(): void
    {
        $this->assertSame('+00:15', WorkDay::formatSignedMinutesPt(15));
        $this->assertSame('−00:10', WorkDay::formatSignedMinutesPt(-10));
        $this->assertSame('00:00', WorkDay::formatSignedMinutesPt(0));
    }

    public function test_tolerance_ux_badge_within_discount_band(): void
    {
        $wd = WorkDay::make([
            'extra_minutes' => 0,
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 8,
                'minutes' => 10,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            ],
        ]);
        $b = $wd->toleranceUxBadgePt();
        $this->assertSame('within', $b['key']);
        $this->assertStringContainsString('Dentro da tolerância', $b['label']);
    }

    public function test_tolerance_ux_badge_applied_discount(): void
    {
        $wd = WorkDay::make([
            'extra_minutes' => 5,
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 15,
                'minutes' => 10,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
                'extra_minutes_final' => 5,
            ],
        ]);
        $b = $wd->toleranceUxBadgePt();
        $this->assertSame('applied_discount', $b['key']);
        $this->assertStringContainsString('Tolerância aplicada', $b['label']);
    }

    public function test_tolerance_ux_badge_outside_dead_band(): void
    {
        $wd = WorkDay::make([
            'extra_minutes' => 15,
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 15,
                'minutes' => 10,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'extra_minutes_final' => 15,
            ],
        ]);
        $b = $wd->toleranceUxBadgePt();
        $this->assertSame('outside_dead_band', $b['key']);
        $this->assertStringContainsString('Fora da tolerância', $b['label']);
    }

    public function test_tolerance_post_close_mismatch_when_balance_differs(): void
    {
        $wd = WorkDay::make([
            'is_closed' => true,
            'extra_minutes' => 10,
            'tolerance_snapshot' => [
                'extra_minutes_final' => 5,
                'calculation_path' => 'weekday_tolerance',
            ],
        ]);
        $this->assertNotNull($wd->tolerancePostCloseMismatchPt());
        $this->assertTrue($wd->toleranceBalanceDiffersFromSnapshot());
    }

    public function test_tolerance_post_close_no_warning_when_balance_matches(): void
    {
        $wd = WorkDay::make([
            'is_closed' => true,
            'extra_minutes' => 5,
            'tolerance_snapshot' => [
                'extra_minutes_final' => 5,
                'calculation_path' => 'weekday_tolerance',
            ],
        ]);
        $this->assertNull($wd->tolerancePostCloseMismatchPt());
        $this->assertFalse($wd->toleranceBalanceDiffersFromSnapshot());
    }

    public function test_snapshot_schema_constants(): void
    {
        $this->assertSame(1, WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION);
        $this->assertSame('v1', WorkDay::TOLERANCE_ENGINE_ID);
    }

    public function test_has_valid_tolerance_snapshot_when_complete(): void
    {
        $wd = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION,
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
            ],
        ]);
        $this->assertTrue($wd->hasValidToleranceSnapshot());
    }

    public function test_has_valid_tolerance_snapshot_false_when_legacy_or_partial(): void
    {
        $legacy = WorkDay::make([
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 15,
                'minutes' => 10,
                'mode_label_pt' => 'Desconto no saldo diário',
            ],
        ]);
        $this->assertFalse($legacy->hasValidToleranceSnapshot());

        $this->assertFalse(WorkDay::make(['tolerance_snapshot' => null])->hasValidToleranceSnapshot());
        $this->assertFalse(WorkDay::make(['tolerance_snapshot' => []])->hasValidToleranceSnapshot());

        $missingEngine = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => 1,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            ],
        ]);
        $this->assertFalse($missingEngine->hasValidToleranceSnapshot());

        $emptyEngine = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => 1,
                'engine' => '',
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            ],
        ]);
        $this->assertFalse($emptyEngine->hasValidToleranceSnapshot());

        $versionAsString = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => '1',
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            ],
        ]);
        $this->assertTrue($versionAsString->hasValidToleranceSnapshot());

        $versionZero = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => 0,
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            ],
        ]);
        $this->assertFalse($versionZero->hasValidToleranceSnapshot());
    }

    public function test_tolerance_ux_badge_key_matches_badge(): void
    {
        $wd = WorkDay::make([
            'extra_minutes' => 5,
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 15,
                'minutes' => 10,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
                'extra_minutes_final' => 5,
            ],
        ]);
        $this->assertSame('applied_discount', $wd->toleranceUxBadgeKey());
        $this->assertSame($wd->toleranceUxBadgePt()['key'], $wd->toleranceUxBadgeKey());
    }

    public function test_tolerance_ux_kind_none_when_no_weekday_badge(): void
    {
        $this->assertSame(WorkDay::TT_KIND_NONE, WorkDay::make([
            'tolerance_snapshot' => [
                'version' => 1,
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'holiday_or_sunday_full',
            ],
        ])->toleranceUxKind());
    }

    public function test_aggregate_tolerance_ux_summary_counts_weekday_tolerance_only(): void
    {
        $v = WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION;
        $e = WorkDay::TOLERANCE_ENGINE_ID;

        $within = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => -5,
                'minutes' => 10,
            ],
        ]);
        $discount = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 20,
                'minutes' => 10,
            ],
        ]);
        $outside = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 20,
                'minutes' => 10,
            ],
        ]);
        $ignored = WorkDay::make([
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'holiday_or_sunday_full',
                'extra_minutes_final' => 120,
            ],
        ]);

        $block = WorkDay::aggregateToleranceUxSummary([$within, $discount, $outside, $ignored]);

        $this->assertSame([
            'within' => 1,
            'applied_discount' => 1,
            'outside_dead_band' => 1,
            'total_days' => 3,
            'pct_within' => 33.4,
            'pct_applied_discount' => 33.3,
            'pct_outside_dead_band' => 33.3,
            'avg_abs_deviation_minutes' => 15.0,
            'avg_signed_deviation_minutes' => 11.7,
        ], $block['summary']);
        $this->assertSame([
            'rows_without_snapshot' => 0,
            'ignored_legacy_days' => 0,
            'excluded_open_days' => 0,
            'excluded_by_auditable_only' => 0,
            'non_classified_days' => 1,
            'considered_days' => 3,
            'valid_work_day_rows' => 4,
        ], $block['meta']);
        $partitionSum = $block['meta']['rows_without_snapshot']
            + $block['meta']['ignored_legacy_days']
            + $block['meta']['non_classified_days']
            + $block['meta']['considered_days']
            + $block['meta']['excluded_open_days'];
        $this->assertSame($block['meta']['valid_work_day_rows'], $partitionSum);
        $this->assertSame(
            100.0,
            round(
                $block['summary']['pct_within']
                + $block['summary']['pct_applied_discount']
                + $block['summary']['pct_outside_dead_band'],
                1
            )
        );
    }

    public function test_aggregate_skips_legacy_snapshot_without_schema_fields(): void
    {
        $legacyWeekday = WorkDay::make([
            'tolerance_snapshot' => [
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 5,
                'minutes' => 10,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
            ],
        ]);

        $block = WorkDay::aggregateToleranceUxSummary([$legacyWeekday]);

        $this->assertSame(0, $block['summary']['total_days']);
        $this->assertSame(0.0, $block['summary']['pct_within']);
        $this->assertSame(0.0, $block['summary']['avg_abs_deviation_minutes']);
        $this->assertSame(0.0, $block['summary']['avg_signed_deviation_minutes']);
        $this->assertSame(1, $block['meta']['valid_work_day_rows']);
        $this->assertSame(1, $block['meta']['ignored_legacy_days']);
        $this->assertSame(0, $block['meta']['considered_days']);
        $this->assertSame(0, $block['meta']['non_classified_days']);
        $this->assertSame(0, $block['meta']['rows_without_snapshot']);
        $this->assertSame(0, $block['meta']['excluded_by_auditable_only']);
        $this->assertSame(0, $block['meta']['excluded_open_days']);
        $partitionSum = $block['meta']['rows_without_snapshot']
            + $block['meta']['ignored_legacy_days']
            + $block['meta']['non_classified_days']
            + $block['meta']['considered_days']
            + $block['meta']['excluded_open_days'];
        $this->assertSame($block['meta']['valid_work_day_rows'], $partitionSum);
    }

    public function test_aggregate_auditable_only_excludes_open_days(): void
    {
        $v = WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION;
        $e = WorkDay::TOLERANCE_ENGINE_ID;
        $snap = [
            'version' => $v,
            'engine' => $e,
            'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
            'calculation_path' => 'weekday_tolerance',
            'raw_diff_minutes' => 5,
            'minutes' => 10,
        ];

        $closed = WorkDay::make(['is_closed' => true, 'tolerance_snapshot' => $snap]);
        $open = WorkDay::make(['is_closed' => false, 'tolerance_snapshot' => $snap]);

        $all = WorkDay::aggregateToleranceUxSummary([$closed, $open], false);
        $audit = WorkDay::aggregateToleranceUxSummary([$closed, $open], true);

        $this->assertSame(2, $all['summary']['total_days']);
        $this->assertSame(1, $audit['summary']['total_days']);
        $this->assertSame(100.0, $audit['summary']['pct_within']);
        $this->assertSame(0, $all['meta']['non_classified_days']);
        $this->assertSame(0, $audit['meta']['non_classified_days']);
        $this->assertSame(0, $all['meta']['excluded_by_auditable_only']);
        $this->assertSame(1, $audit['meta']['excluded_by_auditable_only']);
        $this->assertSame(0, $all['meta']['excluded_open_days']);
        $this->assertSame(1, $audit['meta']['excluded_open_days']);
        $this->assertSame(2, $all['meta']['valid_work_day_rows']);
        $this->assertSame(2, $audit['meta']['valid_work_day_rows']);
        foreach ([$all, $audit] as $blk) {
            $psum = $blk['meta']['rows_without_snapshot']
                + $blk['meta']['ignored_legacy_days']
                + $blk['meta']['non_classified_days']
                + $blk['meta']['considered_days']
                + $blk['meta']['excluded_open_days'];
            $this->assertSame($blk['meta']['valid_work_day_rows'], $psum);
        }
    }

    public function test_aggregate_counts_rows_without_snapshot(): void
    {
        $v = WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION;
        $e = WorkDay::TOLERANCE_ENGINE_ID;

        $noSnap = WorkDay::make(['tolerance_snapshot' => null]);
        $within = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 2,
                'minutes' => 10,
            ],
        ]);

        $block = WorkDay::aggregateToleranceUxSummary([$noSnap, $within], false);

        $this->assertSame(1, $block['meta']['rows_without_snapshot']);
        $this->assertSame(1, $block['meta']['considered_days']);
        $this->assertSame(2, $block['meta']['valid_work_day_rows']);
        $partitionSum = $block['meta']['rows_without_snapshot']
            + $block['meta']['ignored_legacy_days']
            + $block['meta']['non_classified_days']
            + $block['meta']['considered_days']
            + $block['meta']['excluded_open_days'];
        $this->assertSame($block['meta']['valid_work_day_rows'], $partitionSum);
    }

    public function test_aggregate_skips_non_work_day_objects_in_iterable(): void
    {
        $v = WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION;
        $e = WorkDay::TOLERANCE_ENGINE_ID;
        $wd = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => $v,
                'engine' => $e,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 3,
                'minutes' => 10,
            ],
        ]);

        $block = WorkDay::aggregateToleranceUxSummary([$wd, new \stdClass], false);

        $this->assertSame(1, $block['meta']['valid_work_day_rows']);
        $this->assertSame(1, $block['meta']['considered_days']);
    }

    /**
     * Motor/versão futuros no snapshot ainda reconhecidos por {@see WorkDay::hasValidToleranceSnapshot()}
     * devem continuar a fluir na agregação enquanto o formato weekday for compatível.
     */
    public function test_aggregate_accepts_future_snapshot_version_and_engine_when_weekday_shape_compatible(): void
    {
        $wd = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => 2,
                'engine' => 'v2',
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 4,
                'minutes' => 10,
            ],
        ]);

        $this->assertTrue($wd->hasValidToleranceSnapshot());

        $block = WorkDay::aggregateToleranceUxSummary([$wd], false);

        $this->assertSame(1, $block['meta']['considered_days']);
        $this->assertSame(1, $block['summary']['total_days']);
        $this->assertSame(1, $block['summary']['within']);
        $this->assertSame(1, $block['meta']['valid_work_day_rows']);
    }
}
