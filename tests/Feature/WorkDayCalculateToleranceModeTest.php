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
use App\Support\CltSkipReason;
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

    /** Modo CLT único: pequenos desvios no bucket progressivo + almoço por duração → saldo 0. */
    public function test_weekday_clt_event_small_deviations_bank_zero(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT);
        $this->seedFourPunchesCltExample($employee, ['08:04:00', '12:03:00', '13:04:00', '17:00:00']);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame(0, $workDay->extra_minutes);
        $this->assertTrue($workDay->tt_clt_applied);
        $this->assertTrue((bool) data_get($workDay->tolerance_snapshot, 'clt_applied'));
        $this->assertFalse((bool) data_get($workDay->tolerance_snapshot, 'clt_skipped'));
        $this->assertSame('clt_primary', $workDay->tolerance_snapshot['integration_mode']);
        $this->assertSame('weekday_clt_event_progressive_duration', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_CLT_PROGRESSIVE_DURATION, $workDay->tolerance_snapshot['engine']);
        $this->assertSame('progressive_daily_cap_duration_v1', data_get($workDay->tolerance_snapshot, 'clt.rule_applied'));
        $this->assertSame('3_events_lunch_duration', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
        $this->assertSame(
            'actual_lunch_exit_plus_configured_duration',
            data_get($workDay->tolerance_snapshot, 'clt.lunch_return_expected_source')
        );
        $this->assertSame(WorkToleranceResolver::MODE_CLT_EVENT, $workDay->tolerance_snapshot['mode']);
        $this->assertSame('high', $workDay->tolerance_snapshot['calculation_confidence']);
        $this->assertSame('high', $workDay->tt_calculation_confidence);
    }

    /** Quatro batidas com intervalo: modelo 3 eventos (saída para almoço não comparada ao gabarito). */
    public function test_weekday_clt_event_merged_lunch_three_slot_model(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT);
        $this->seedFourPunchesCltExample($employee, ['08:04:00', '12:03:00', '13:04:00', '17:00:00']);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame('weekday_clt_event_progressive_duration', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertTrue((bool) data_get($workDay->tolerance_snapshot, 'clt_applied'));
        $this->assertSame('3_events_lunch_duration', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
        $this->assertSame(
            'actual_lunch_exit_plus_configured_duration',
            data_get($workDay->tolerance_snapshot, 'clt.lunch_return_expected_source')
        );
    }

    /** Delta do almoço = configurado − duração real (efeito jornada). */
    public function test_weekday_clt_event_short_lunch_positive_work_effect_delta(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT);
        $this->seedFourPunchesCltExample($employee, ['08:00:00', '12:00:00', '12:40:00', '17:00:00']);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame('weekday_clt_event_progressive_duration', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_CLT_PROGRESSIVE_DURATION, $workDay->tolerance_snapshot['engine']);
        $this->assertSame('3_events_lunch_duration', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
        $this->assertSame('clt_event_progressive_duration', data_get($workDay->tolerance_snapshot, 'clt.calculation_engine_family'));
        $this->assertSame('progressive_daily_cap_duration_v1', data_get($workDay->tolerance_snapshot, 'clt.engine_variant'));

        $lunchEv = collect(data_get($workDay->tolerance_snapshot, 'clt.events_progressive'))->firstWhere('type', 'lunch_duration');
        $this->assertNotNull($lunchEv);
        $this->assertSame(20, (int) $lunchEv['delta']);
        $this->assertSame('work_effect_duration', $lunchEv['delta_source']);
    }

    public function test_weekday_clt_fallback_when_only_two_punches(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT);
        $this->seedRecords0800To1615($employee);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame('weekday_tolerance', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertTrue((bool) data_get($workDay->tolerance_snapshot, 'clt_skipped'));
        $this->assertFalse((bool) data_get($workDay->tolerance_snapshot, 'clt_applied'));
        $this->assertFalse($workDay->tt_clt_applied);
        $this->assertSame(CltSkipReason::WRONG_RECORD_COUNT, $workDay->tolerance_snapshot['clt_skip_reason']);
        $this->assertSame(
            'Quantidade de batidas diferente do gabarito esperado.',
            data_get($workDay->tolerance_snapshot, 'clt_skip_detail.reason_label_pt')
        );
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, data_get($workDay->tolerance_snapshot, 'clt_skip_category'));
        $this->assertSame(CltSkipReason::CATEGORY_STRUCTURAL, data_get($workDay->tolerance_snapshot, 'clt_skip_detail.skip_category'));
        $this->assertSame('low', $workDay->tolerance_snapshot['calculation_confidence']);
        $this->assertSame('low', $workDay->tt_calculation_confidence);
        $this->assertSame(15, $workDay->extra_minutes);
    }

    /** Gabarito CLT com 2 batidas (sem almoço na escala): entrada + saída. */
    public function test_weekday_clt_two_events_within_daily_cap_bank_zero(): void
    {
        $employee = $this->employeeWithTwoEventCltSchedule();
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '08:04:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '17:04:00')->create();

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame(0, $workDay->extra_minutes);
        $this->assertTrue((bool) data_get($workDay->tolerance_snapshot, 'clt_applied'));
        $this->assertSame('weekday_clt_event_progressive_duration', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame('2_events', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
        $this->assertSame(2, (int) data_get($workDay->tolerance_snapshot, 'expected_events'));
        $this->assertSame(2, (int) data_get($workDay->tolerance_snapshot, 'actual_events'));
        $this->assertSame('high', $workDay->tolerance_snapshot['calculation_confidence']);
    }

    /** Entrada +6 min: bucket progressivo recolhe 5 + 1 min ao saldo (não integra os 6 min de uma vez). */
    public function test_weekday_clt_two_events_one_mark_outside_tolerance(): void
    {
        $employee = $this->employeeWithTwoEventCltSchedule();
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, '08:06:00')->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, '17:00:00')->create();

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame(-1, $workDay->extra_minutes);
        $this->assertSame(-1, (int) data_get($workDay->tolerance_snapshot, 'outside_event_minutes'));
        $this->assertSame('2_events', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
    }

    public function test_weekday_clt_two_events_four_punches_fallback_wrong_count(): void
    {
        $employee = $this->employeeWithTwoEventCltSchedule();
        $this->seedFourPunchesCltExample($employee, ['08:00:00', '12:00:00', '13:00:00', '17:00:00']);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame('weekday_tolerance', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertTrue((bool) data_get($workDay->tolerance_snapshot, 'clt_skipped'));
        $this->assertSame(CltSkipReason::WRONG_RECORD_COUNT, $workDay->tolerance_snapshot['clt_skip_reason']);
        $this->assertSame(2, (int) data_get($workDay->tolerance_snapshot, 'expected_events'));
        $this->assertSame(4, (int) data_get($workDay->tolerance_snapshot, 'actual_events'));
        $this->assertSame('low', $workDay->tolerance_snapshot['calculation_confidence']);
    }

    /** Intervalo mais curto que o configurado: efeito jornada positivo absorvido no bucket progressivo → saldo 0. */
    public function test_weekday_clt_event_shorter_lunch_than_config_bank_zero(): void
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT, 90);
        $this->seedFourPunchesCltExample($employee, ['08:00:00', '12:16:00', '13:41:00', '17:00:00']);

        $workDay = app(WorkDayService::class)->calculateAndSave($employee, self::WEEKDAY);

        $this->assertSame('weekday_clt_event_progressive_duration', $workDay->tolerance_snapshot['calculation_path']);
        $this->assertSame(WorkDay::TOLERANCE_ENGINE_CLT_PROGRESSIVE_DURATION, $workDay->tolerance_snapshot['engine']);
        $this->assertSame(
            'actual_lunch_exit_plus_configured_duration',
            data_get($workDay->tolerance_snapshot, 'clt.lunch_return_expected_source')
        );
        $this->assertSame('work_effect_duration', data_get($workDay->tolerance_snapshot, 'clt.lunch_delta_convention'));
        $this->assertSame('3_events_lunch_duration', data_get($workDay->tolerance_snapshot, 'clt.event_model'));
        $this->assertSame(0, $workDay->extra_minutes);
    }

    private function employeeWithSchedule(string $companyToleranceMode, int $lunchMinutes = 60): Employee
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
            'lunch_minutes' => $lunchMinutes,
            'tolerance_minutes' => 10,
            'tolerance_mode' => null,
            'work_days' => [1, 2, 3, 4, 5],
            'active' => true,
        ]);

        return $employee->fresh(['workSchedule', 'dept', 'company']);
    }

    private function employeeWithTwoEventCltSchedule(): Employee
    {
        $employee = $this->employeeWithSchedule(WorkToleranceResolver::MODE_CLT_EVENT);
        $employee->workSchedule->update(['lunch_minutes' => 0]);

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

    /** Gabarito 08 / 12 / 13 / 17 — entrada, saída almoço, retorno, saída. */
    private function seedFourPunchesCltExample(Employee $employee, array $times): void
    {
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, $times[0])->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, $times[1])->create();
        TimeRecord::factory()->for($employee)->entrada()->forDate(self::WEEKDAY, $times[2])->create();
        TimeRecord::factory()->for($employee)->saida()->forDate(self::WEEKDAY, $times[3])->create();
    }
}
