<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayRecalculateCommandTest extends TestCase
{
    use RefreshDatabase;

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
}
