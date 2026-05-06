<?php

namespace Tests\Unit;

use App\Models\WorkDay;
use App\Support\CltSkipReason;
use Tests\TestCase;

class CltSkipReasonTest extends TestCase
{
    public function test_normalize_unknown_codes(): void
    {
        $this->assertSame(CltSkipReason::UNKNOWN, CltSkipReason::normalize('not_a_real_code'));
        $this->assertSame(CltSkipReason::MISSING_GABARITO, CltSkipReason::normalize(CltSkipReason::MISSING_GABARITO));
    }

    public function test_normalize_trim_and_case_insensitive(): void
    {
        $this->assertSame(
            CltSkipReason::WRONG_RECORD_COUNT,
            CltSkipReason::normalize('  WRONG_RECORD_COUNT  ')
        );
        $this->assertSame(
            CltSkipReason::TYPE_SEQUENCE_MISMATCH,
            CltSkipReason::normalize('Type_Sequence_Mismatch')
        );
    }

    public function test_category_rule_vs_structural(): void
    {
        $this->assertSame(CltSkipReason::CATEGORY_RULE, CltSkipReason::category(CltSkipReason::MISSING_GABARITO));
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, CltSkipReason::category(CltSkipReason::WRONG_RECORD_COUNT));
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, CltSkipReason::category(CltSkipReason::TYPE_SEQUENCE_MISMATCH));
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, CltSkipReason::category(CltSkipReason::INCOMPLETE_DAY));
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, CltSkipReason::category(CltSkipReason::UNKNOWN));
    }

    public function test_values_contract_stable(): void
    {
        $this->assertContains(CltSkipReason::MISSING_GABARITO, CltSkipReason::values());
        $this->assertContains(CltSkipReason::INCOMPLETE_DAY, CltSkipReason::values());
    }

    public function test_schema_export_matches_contract(): void
    {
        $schema = CltSkipReason::schema();
        $this->assertSame(CltSkipReason::values(), $schema['reasons']);
        $this->assertSame([CltSkipReason::CATEGORY_STRUCTURAL, CltSkipReason::CATEGORY_RULE], $schema['categories']);
        $this->assertSame(['high', 'medium', 'low'], $schema['confidence']);
    }

    public function test_work_day_confidence_heuristic(): void
    {
        $this->assertSame('high', WorkDay::toleranceConfidenceFromSnapshot([
            'calculation_path' => 'weekday_clt_event_based',
            'clt_applied' => true,
        ]));
        $this->assertSame('low', WorkDay::toleranceConfidenceFromSnapshot([
            'calculation_path' => 'weekday_tolerance',
            'clt_skipped' => true,
            'clt_skip_category' => CltSkipReason::CATEGORY_STRUCTURAL,
        ]));
        $this->assertSame('medium', WorkDay::toleranceConfidenceFromSnapshot([
            'calculation_path' => 'weekday_tolerance',
            'clt_skipped' => true,
            'clt_skip_category' => CltSkipReason::CATEGORY_RULE,
        ]));
        $this->assertSame('low', WorkDay::toleranceConfidenceFromSnapshot([
            'calculation_path' => 'open_day',
        ]));
        $this->assertSame('medium', WorkDay::toleranceConfidenceFromSnapshot([
            'calculation_path' => 'weekday_tolerance',
        ]));
    }
}
