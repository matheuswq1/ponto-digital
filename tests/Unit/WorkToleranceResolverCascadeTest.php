<?php

namespace Tests\Unit;

use App\DTO\WorkToleranceContext;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\WorkToleranceResolver;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Cascata de modo/tolerância sem BD — evita migrações específicas de MySQL em sqlite (:memory:).
 */
class WorkToleranceResolverCascadeTest extends TestCase
{
    private function resolver(): WorkToleranceResolver
    {
        return new WorkToleranceResolver;
    }

    private function calendarNoon(Company $company): Carbon
    {
        return Carbon::parse('2026-06-15 12:00:00', WorkToleranceResolver::effectiveTimezone($company));
    }

    public function test_department_mode_overrides_company(): void
    {
        $company = new Company([
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
        ]);

        $dept = new Department([
            'entry_time' => '09:00:00',
            'exit_time' => '18:00:00',
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        $employee = new Employee;
        $employee->setRelation('company', $company);
        $employee->setRelation('dept', $dept);
        $employee->setRelation('workSchedule', null);

        $ctx = $this->resolver()->resolve($employee, $this->calendarNoon($company));

        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $ctx->toleranceMode);
        $this->assertSame(WorkToleranceContext::SOURCE_DEPARTMENT, $ctx->modeResolvedFrom);
    }

    public function test_schedule_mode_when_no_department_template(): void
    {
        $company = new Company([
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
        ]);

        $schedule = new WorkSchedule([
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'work_days' => [1, 2, 3, 4, 5],
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
            'tolerance_minutes' => 10,
        ]);

        $employee = new Employee;
        $employee->setRelation('company', $company);
        $employee->setRelation('dept', null);
        $employee->setRelation('workSchedule', $schedule);

        $ctx = $this->resolver()->resolve($employee, $this->calendarNoon($company));

        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $ctx->toleranceMode);
        $this->assertSame(WorkToleranceContext::SOURCE_WORK_SCHEDULE, $ctx->modeResolvedFrom);
    }

    public function test_company_mode_when_no_overrides(): void
    {
        $company = new Company([
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DISCOUNT,
        ]);

        $employee = new Employee;
        $employee->setRelation('company', $company);
        $employee->setRelation('dept', null);
        $employee->setRelation('workSchedule', null);

        $ctx = $this->resolver()->resolve($employee, $this->calendarNoon($company));

        $this->assertSame(WorkToleranceResolver::MODE_DAILY_DISCOUNT, $ctx->toleranceMode);
        $this->assertSame(WorkToleranceContext::SOURCE_COMPANY, $ctx->modeResolvedFrom);
    }

    public function test_effective_timezone_from_company_column(): void
    {
        $company = new Company([
            'timezone' => 'America/Manaus',
            'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
        ]);

        $employee = new Employee;
        $employee->setRelation('company', $company);
        $employee->setRelation('dept', null);
        $employee->setRelation('workSchedule', null);

        $ctx = $this->resolver()->resolve($employee, $this->calendarNoon($company));

        $this->assertSame('America/Manaus', $ctx->timezone);
    }

    public function test_calendar_date_follows_company_timezone(): void
    {
        $originalTz = config('app.timezone');
        config(['app.timezone' => 'UTC']);

        try {
            $company = new Company([
                'timezone' => 'America/Sao_Paulo',
                'tolerance_mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
            ]);

            $employee = new Employee;
            $employee->setRelation('company', $company);
            $employee->setRelation('dept', null);
            $employee->setRelation('workSchedule', null);

            $instantUtc = Carbon::parse('2026-01-10 02:00:00', 'UTC');
            $ctx = $this->resolver()->resolve($employee, $instantUtc);

            $this->assertSame('2026-01-09', $ctx->calendarDate);
        } finally {
            config(['app.timezone' => $originalTz]);
        }
    }
}
