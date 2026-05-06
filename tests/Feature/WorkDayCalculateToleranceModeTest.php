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

class WorkDayCalculateToleranceModeTest extends TestCase
{
    use RefreshDatabase;

    /** Terça-feira — dia útil na escala [1–5]. */
    private const WEEKDAY = '2026-06-02';

    /**
     * 08:00–16:15 = 495 min trabalhados; esperado 480 → diff +15; tolerância 10 min.
     */
    public function test_weekday_dead_band_credito_integral_acima_da_faixa(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DEAD_BAND);
        $this->seedRecords0800To1615($employee);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame(15, $workDay->extra_minutes);
        $this->assertSame(15, $workDay->tolerance_snapshot['raw_diff_minutes']);
        $this->assertSame(15, $workDay->tolerance_snapshot['extra_minutes_final']);
        $this->assertSame('weekday_tolerance', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DEAD_BAND, $workDay->tolerance_snapshot['mode']);
        $this->assertTrue($workDay->hasValidToleranceSnapshot());
    }

    public function test_weekday_discount_aplica_desconto_sobre_tolerancia(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DISCOUNT);
        $this->seedRecords0800To1615($employee);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame(5, $workDay->extra_minutes);
        $this->assertSame(15, $workDay->tolerance_snapshot['raw_diff_minutes']);
        $this->assertSame(5, $workDay->tolerance_snapshot['extra_minutes_final']);
        $this->assertSame('weekday_tolerance', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $workDay->tolerance_snapshot['mode']);
        $this->assertSame('v1', $workDay->tt_engine);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $workDay->tt_mode);
    }

    public function test_preserving_closed_snapshot_maintains_audit_json_even_when_company_mode_changes(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_DAILY_DEAD_BAND);
        $this->seedRecords0800To1615($employee);

        $svc = app(WorkDayService::class);
        $svc->calculateAndSave($employee, self::WEEKDAY, false);

        $wd = WorkDay::query()->where('employee_id', $employee->id)->whereDate('date', self::WEEKDAY)->firstOrFail();
        $frozenSnapshot = $wd->tolerance_snapshot;

        $employee->company->update(['tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT]);
        $employee->refresh();
        $employee->loadMissing(['workSchedule', 'dept', 'company']);

        $svc->calculateAndSave($employee, self::WEEKDAY, true);

        $wd->refresh();
        $this->assertSame($frozenSnapshot, $wd->tolerance_snapshot);
        $this->assertSame(15, (int) data_get($wd->tolerance_snapshot, 'extra_minutes_final'));
        $this->assertSame(5, $wd->extra_minutes);
        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DEAD_BAND, $wd->tt_mode);
    }

    private function employeeWithSchedule(string $companyToleranceMode): Employee
    {
        $company = Company::factory()->create([
            'tolerance_mode' => $companyToleranceMode,
            'timezone' => 'America/Sao_Paulo',
        ]);

        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
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
