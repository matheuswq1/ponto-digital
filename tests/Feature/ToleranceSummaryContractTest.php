<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\WorkDay;
use App\Services\WorkDayService;
use App\Services\WorkToleranceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ToleranceSummaryContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contrato público: ordem das chaves em meta.reconciliation (json_encode em PHP preserva ordem de inserção).
     *
     * @return list<string>
     */
    private static function expectedReconciliationKeyOrder(): array
    {
        return [
            'total_rows_in_period',
            'valid_work_day_rows',
            'rows_without_snapshot',
            'ignored_legacy_days',
            'excluded_open_days',
            'non_classified_days',
            'considered_days',
            'excluded_by_auditable_only',
            'sum_of_buckets',
            'identity_holds',
        ];
    }

    public function test_tolerance_summary_reconciliation_contract_stable_order_and_identity(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        WorkDay::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-06-02',
            'is_closed' => true,
            'status' => 'normal',
            'tolerance_snapshot' => [
                'version' => WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION,
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 3,
                'minutes' => 10,
            ],
        ]);

        $response = $this->getJson('/api/v1/reports/tolerance-summary?'.http_build_query([
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-02',
        ]));

        $response->assertOk()
            ->assertHeader('X-API-Version', '1');

        $rec = $response->json('meta.reconciliation');
        $this->assertSame(self::expectedReconciliationKeyOrder(), array_keys($rec));

        $bucketSum = $rec['rows_without_snapshot']
            + $rec['ignored_legacy_days']
            + $rec['excluded_open_days']
            + $rec['non_classified_days']
            + $rec['considered_days'];
        $this->assertSame($rec['sum_of_buckets'], $bucketSum);
        $this->assertSame($rec['sum_of_buckets'], $rec['valid_work_day_rows']);
        $this->assertTrue($rec['identity_holds']);
        $this->assertTrue($response->json('meta.reconciliation_complete'));
        $this->assertFalse($response->json('meta.iterable_contains_non_workday'));

        $meta = $response->json('meta');
        $this->assertArrayNotHasKey('reconciliation_mismatch', $meta);
    }

    /**
     * Iterable “sujo” não é produzido pelo Eloquent em condições normais; simula-se o retorno de
     * {@see WorkDayService::getMonthSummary} para garantir que o endpoint não mascara período incompleto.
     *
     * Nota: `identity_holds` refere-se à identidade da partição sobre linhas {@see WorkDay} válidas
     * (`sum_of_buckets` === `valid_work_day_rows`), não à igualdade com `total_rows_in_period`.
     */
    public function test_tolerance_summary_dirty_iterable_surfaces_flag_and_reconciliation_mismatch(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $wd = WorkDay::make([
            'is_closed' => true,
            'tolerance_snapshot' => [
                'version' => WorkDay::TOLERANCE_SNAPSHOT_SCHEMA_VERSION,
                'engine' => WorkDay::TOLERANCE_ENGINE_ID,
                'mode' => WorkToleranceResolver::MODE_DAILY_DEAD_BAND,
                'calculation_path' => 'weekday_tolerance',
                'raw_diff_minutes' => 3,
                'minutes' => 10,
            ],
        ]);

        $this->mock(WorkDayService::class, function ($mock) use ($wd): void {
            $mock->shouldReceive('getMonthSummary')
                ->once()
                ->with(Mockery::type(Employee::class), 2026, 6)
                ->andReturn([
                    'year' => 2026,
                    'month' => 6,
                    'work_days' => collect([$wd, new \stdClass]),
                    'summary' => [],
                ]);
        });

        $response = $this->getJson('/api/v1/reports/tolerance-summary?'.http_build_query([
            'year' => 2026,
            'month' => 6,
        ]));

        $response->assertOk();

        $meta = $response->json('meta');
        $this->assertTrue($meta['iterable_contains_non_workday']);
        $this->assertFalse($meta['reconciliation_complete']);
        $this->assertTrue($meta['reconciliation']['identity_holds']);

        $this->assertArrayHasKey('reconciliation_mismatch', $meta);
        $this->assertSame(2, $meta['reconciliation_mismatch']['expected']);
        $this->assertSame(1, $meta['reconciliation_mismatch']['actual']);
        $this->assertSame(1, $meta['reconciliation_mismatch']['delta']);
        $this->assertSame(1, $meta['reconciliation_mismatch']['valid_work_day_rows']);
    }
}
