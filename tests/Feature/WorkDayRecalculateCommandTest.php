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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayRecalculateCommandTest extends TestCase
{
    use RefreshDatabase;

    private const WEEKDAY = '2026-06-02';

    public function test_requires_from_and_to(): void
    {
        $this->artisan('workday:recalculate')
            ->assertExitCode(1);
    }

    public function test_dry_run_counts_rows_without_processing(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        WorkDay::query()->create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2026-06-02'),
            'total_minutes' => 0,
            'expected_minutes' => 0,
            'extra_minutes' => 0,
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => 1,
                'engine' => 'v1',
                'mode' => 'daily_dead_band',
                'calculation_path' => 'weekday_tolerance',
            ],
        ]);

        $this->artisan('workday:recalculate', [
            '--from' => '2026-06-01',
            '--to' => '2026-06-30',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
    }

    public function test_dry_run_company_filter_counts_only_matching_work_days(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $empA = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'company_id' => $companyA->id,
        ]);
        $empB = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'company_id' => $companyB->id,
        ]);

        foreach ([$empA, $empB] as $emp) {
            WorkDay::query()->create([
                'employee_id' => $emp->id,
                'date' => Carbon::parse(self::WEEKDAY),
                'total_minutes' => 0,
                'expected_minutes' => 0,
                'extra_minutes' => 0,
                'is_closed' => true,
                'tolerance_snapshot' => [
                    'version' => 1,
                    'engine' => 'v1',
                    'mode' => 'daily_dead_band',
                    'calculation_path' => 'weekday_tolerance',
                ],
            ]);
        }

        $this->artisan('workday:recalculate', [
            '--from' => '2026-06-01',
            '--to' => '2026-06-30',
            '--company' => (string) $companyA->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[dry-run] 1 linha(s)')
            ->assertSuccessful();
    }

    public function test_default_preserves_closed_tolerance_snapshot_when_company_mode_changes(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DEAD_BAND);
        $this->seedRecords0800To1615($employee);

        app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY, false);

        $employee->company->update(['tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT]);
        $employee->refresh()->loadMissing(['workSchedule', 'dept', 'company']);

        $frozenSnapshot = WorkDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', self::WEEKDAY)
            ->value('tolerance_snapshot');

        $this->artisan('workday:recalculate', [
            '--from' => self::WEEKDAY,
            '--to' => self::WEEKDAY,
            '--employee' => (string) $employee->id,
        ])
            ->expectsOutputToContain('Recálculo de 1 dia')
            ->assertSuccessful();

        $wd = WorkDay::query()->where('employee_id', $employee->id)->whereDate('date', self::WEEKDAY)->firstOrFail();
        $this->assertSame($frozenSnapshot, $wd->tolerance_snapshot);
        $this->assertSame(5, $wd->extra_minutes);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DEAD_BAND, $wd->tt_mode);
    }

    public function test_force_refresh_snapshot_rewrites_snapshot_and_merges_audit_fields(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DEAD_BAND);
        $this->seedRecords0800To1615($employee);

        app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY, false);

        $employee->company->update(['tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT]);
        $employee->refresh()->loadMissing(['workSchedule', 'dept', 'company']);

        $this->artisan('workday:recalculate', [
            '--from' => self::WEEKDAY,
            '--to' => self::WEEKDAY,
            '--employee' => (string) $employee->id,
            '--force-refresh-snapshot' => true,
        ])->assertSuccessful();

        $wd = WorkDay::query()->where('employee_id', $employee->id)->whereDate('date', self::WEEKDAY)->firstOrFail();
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $wd->tolerance_snapshot['mode']);
        $this->assertSame(5, $wd->extra_minutes);
        $this->assertSame('cli:workday:recalculate', $wd->tolerance_snapshot['recalculated_via']);
        $this->assertSame('v1', $wd->tolerance_snapshot['recalculated_from_engine']);
        $this->assertSame('v1', $wd->tolerance_snapshot['recalculated_to_engine']);
        $this->assertNotEmpty($wd->tolerance_snapshot['recalculated_at']);
    }

    private function employeeWithSchedule(string $companyToleranceMode): Employee
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
            'lunch_minutes' => 60,
            'tolerance_minutes' => 10,
            'tolerance_mode' => null,
            'work_days' => [1, 2, 3, 4, 5],
            'active' => true,
        ]);

        return $employee->fresh(['workSchedule', 'dept', 'company']);
    }

    private function seedRecords0800To1615(Employee $employee): void
    {
        TimeRecord::factory()
            ->for($employee)
            ->entrada()
            ->forDate(self::WEEKDAY, '08:00:00')
            ->create();

        TimeRecord::factory()
            ->for($employee)
            ->saida()
            ->forDate(self::WEEKDAY, '16:15:00')
            ->create();
    }
}
