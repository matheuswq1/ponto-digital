<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkSchedule;
use App\Services\WorkDayService;
use App\Services\WorkToleranceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayTolerancePolicyContractTest extends TestCase
{
    use RefreshDatabase;

    /** Terça-feira — dia útil na escala [1–5]. */
    private const WEEKDAY = '2026-06-02';

    public function test_policy_contract_on_simple_weekday_tolerance(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DEAD_BAND);
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '08:00:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '16:15:00')->create();

        $wd = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);
        $p = $wd->tolerance_snapshot['policy'];

        $this->assertSame(WorkDay::TOLERANCE_POLICY_CONTRACT_VERSION, $p['version']);
        $this->assertSame(WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION, $p['generated_from_snapshot_version']);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DEAD_BAND, $p['mode']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_ID, $p['engine']);
        $this->assertSame(10, $p['tolerance']['daily_minutes']);
        $this->assertNull($p['tolerance']['event_minutes']);
        $this->assertNull($p['tolerance']['daily_cap_minutes']);
        $this->assertNull($p['integration']['mode']);
        $this->assertNull($p['integration']['fallback_mode']);
        $this->assertNull($p['lunch']['strategy']);
        $this->assertNull($p['lunch']['configured_minutes']);
        $this->assertSame('America/Sao_Paulo', $p['timezone']);
        $this->assertSame('weekday_tolerance', $p['calculation']['path']);
        $this->assertSame('medium', $p['calculation']['confidence']);

        $this->assertSame($p, $wd->toleranceMetaForApi()['policy']);
    }

    public function test_policy_contract_when_clt_strict_applied(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT_STRICT, 90);
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '08:00:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '12:16:00')->create();
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '13:41:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '17:00:00')->create();

        $wd = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);
        $p = $wd->tolerance_snapshot['policy'];

        $this->assertSame(WorkDay::TOLERANCE_POLICY_CONTRACT_VERSION, $p['version']);
        $this->assertSame(WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION, $p['generated_from_snapshot_version']);
        $this->assertSame(WorkToleranceResolver::MODE_CLT_EVENT_STRICT, $p['mode']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_CLT_EVENT_STRICT, $p['engine']);
        $this->assertSame(10, $p['tolerance']['daily_minutes']);
        $this->assertSame(5, $p['tolerance']['event_minutes']);
        $this->assertSame(10, $p['tolerance']['daily_cap_minutes']);
        $this->assertSame('clt_primary', $p['integration']['mode']);
        $this->assertNull($p['integration']['fallback_mode']);
        $this->assertSame('actual_lunch_exit_plus_duration', $p['lunch']['strategy']);
        $this->assertSame(90, $p['lunch']['configured_minutes']);
        $this->assertSame('weekday_clt_event_strict', $p['calculation']['path']);
        $this->assertSame('high', $p['calculation']['confidence']);
    }

    public function test_policy_contract_when_clt_skipped_fallback(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT_BASED);
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '08:00:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '16:15:00')->create();

        $wd = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);
        $p = $wd->tolerance_snapshot['policy'];

        $this->assertSame(WorkDay::TOLERANCE_POLICY_CONTRACT_VERSION, $p['version']);
        $this->assertSame(WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION, $p['generated_from_snapshot_version']);
        $this->assertSame(WorkToleranceResolver::MODE_CLT_EVENT_BASED, $p['mode']);
        $this->assertSame('weekday_tolerance', $p['calculation']['path']);
        $this->assertSame('diff_fallback_after_clt_skip', $p['integration']['mode']);
        $this->assertNull($p['integration']['fallback_mode']);
        $this->assertNull($p['lunch']['strategy']);
        $this->assertNull($p['lunch']['configured_minutes']);
        $this->assertSame('low', $p['calculation']['confidence']);
    }

    private function employeeWithSchedule(string $companyToleranceMode, int $lunchMinutes = 60): Employee
    {
        $company = Company::factory()->create([
            'tolerance_mode' => $companyToleranceMode,
            'timezone' => 'America/Sao_Paulo',
        ]);

        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'company_id' => $company->id,
            'department_id' => null,
        ]);

        WorkSchedule::query()->create([
            'employee_id' => $employee->id,
            'name' => 'Escala teste',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'lunch_minutes' => $lunchMinutes,
            'tolerance_minutes' => 10,
            'tolerance_mode' => null,
            'work_days' => [1, 2, 3, 4, 5],
            'active' => true,
        ]);

        return $employee->fresh(['workSchedule', 'dept', 'company']);
    }
}
