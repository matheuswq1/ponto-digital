<?php

namespace Tests\Unit;

use App\Http\Resources\WorkDayResource;
use App\Models\WorkDay;
use Illuminate\Http\Request;
use Tests\TestCase;

class WorkDayToleranceMetaForApiTest extends TestCase
{
    public function test_tolerance_meta_mirrors_snapshot_with_version(): void
    {
        $snapshot = [
            'version' => 1,
            'engine' => WorkDay::TOLERANCE_ENGINE_CLT_EVENT_BASED,
            'mode' => 'clt_event_based',
            'calculation_path' => 'weekday_clt_event_based',
            'calculation_confidence' => 'high',
            'expected_events' => 2,
            'actual_events' => 2,
            'clt_applied' => true,
            'clt_skipped' => false,
            'clt_skip_reason' => null,
            'clt_skip_category' => null,
            'integration_mode' => 'clt_primary',
            'calculation_base_pt' => 'Eventos',
        ];

        $wd = WorkDay::make([
            'tolerance_snapshot' => $snapshot,
        ]);

        $meta = $wd->toleranceMetaForApi();

        $this->assertSame(WorkDay::TOLERANCE_META_API_VERSION, $meta['meta_version']);
        $this->assertTrue($meta['is_complete']);
        $this->assertNull($meta['policy']);
        foreach ([
            'engine', 'mode', 'calculation_path', 'calculation_confidence',
            'expected_events', 'actual_events',
            'clt_applied', 'clt_skipped', 'clt_skip_reason', 'clt_skip_category',
            'integration_mode', 'calculation_base_pt',
        ] as $key) {
            $this->assertSame(data_get($snapshot, $key), $meta[$key], $key);
        }

        $this->assertSame(WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT, $meta['effective_tolerance_engine_family']);

        $this->assertNull($meta['event_tolerance_minutes']);
    }

    public function test_tolerance_meta_is_complete_false_when_snapshot_invalid(): void
    {
        $wd = WorkDay::make([
            'tolerance_snapshot' => [
                'engine' => 'v1',
                'mode' => 'daily_dead_band',
            ],
        ]);

        $meta = $wd->toleranceMetaForApi();

        $this->assertFalse($meta['is_complete']);
        $this->assertNull($meta['policy']);
    }

    public function test_work_day_resource_uses_same_meta_as_model_method(): void
    {
        $snapshot = [
            'engine' => 'v1',
            'mode' => 'daily_dead_band',
            'calculation_path' => 'weekday_tolerance',
            'calculation_confidence' => 'medium',
        ];

        $wd = WorkDay::make([
            'id' => 1,
            'employee_id' => 2,
            'date' => now()->startOfDay(),
            'tolerance_snapshot' => $snapshot,
        ]);

        $payload = (new WorkDayResource($wd))->toArray(Request::create('/'));

        $this->assertSame($wd->toleranceMetaForApi(), $payload['tolerance_meta']);
    }

    public function test_tolerance_meta_prefers_policy_when_present_over_legacy_roots(): void
    {
        $policy = [
            'version' => WorkDay::TOLERANCE_POLICY_CONTRACT_VERSION,
            'generated_from_snapshot_version' => WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION,
            'engine' => WorkDay::TOLERANCE_ENGINE_CLT_EVENT_STRICT,
            'mode' => 'clt_event_strict',
            'tolerance' => [
                'daily_minutes' => 10,
                'event_minutes' => 5,
                'daily_cap_minutes' => 10,
            ],
            'integration' => [
                'mode' => 'clt_primary',
                'fallback_mode' => null,
            ],
            'lunch' => [
                'strategy' => null,
                'configured_minutes' => null,
            ],
            'timezone' => 'America/Sao_Paulo',
            'calculation' => [
                'path' => 'weekday_clt_event_strict',
                'confidence' => 'high',
                'family' => WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT,
            ],
        ];

        $snapshot = [
            'version' => 1,
            'engine' => WorkDay::TOLERANCE_ENGINE_ID,
            'mode' => 'daily_dead_band',
            'calculation_path' => 'weekday_tolerance',
            'calculation_confidence' => 'medium',
            'integration_mode' => null,
            'event_tolerance_minutes' => null,
            'daily_cap_minutes' => null,
            'policy' => $policy,
        ];

        $wd = WorkDay::make(['tolerance_snapshot' => $snapshot]);
        $meta = $wd->toleranceMetaForApi();

        $this->assertSame($policy, $meta['policy']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_CLT_EVENT_STRICT, $meta['engine']);
        $this->assertSame('clt_event_strict', $meta['mode']);
        $this->assertSame('weekday_clt_event_strict', $meta['calculation_path']);
        $this->assertSame('high', $meta['calculation_confidence']);
        $this->assertSame(WorkDay::EFFECTIVE_TOLERANCE_ENGINE_FAMILY_CLT_EVENT, $meta['effective_tolerance_engine_family']);
        $this->assertSame('clt_primary', $meta['integration_mode']);
        $this->assertSame(5, $meta['event_tolerance_minutes']);
        $this->assertSame(10, $meta['daily_cap_minutes']);
    }
}
