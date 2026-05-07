<?php

namespace Tests\Unit;

use App\Models\WorkDay;
use Tests\TestCase;

class WorkDayEffectiveToleranceEngineFamilyTest extends TestCase
{
    public function test_maps_calculation_paths_to_stable_families(): void
    {
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'weekday_clt_event_based'])
        );
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'weekday_clt_event_strict'])
        );
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'weekday_clt_event_progressive_cap'])
        );
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'weekday_clt_event_progressive_duration'])
        );

        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_DAILY_DIFF,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'weekday_tolerance'])
        );

        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CALENDAR_FULL_DAY,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'holiday_or_sunday_full'])
        );
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CALENDAR_FULL_DAY,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'saturday_or_off_schedule_full'])
        );

        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_OPEN_DAY,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'open_day'])
        );

        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_UNKNOWN,
            WorkDay::effectiveToleranceEngineFamily(['calculation_path' => 'future_or_legacy_path'])
        );
        $this->assertSame(
            WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_UNKNOWN,
            WorkDay::effectiveToleranceEngineFamily([])
        );
    }
}
