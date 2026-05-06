<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ToleranceSummaryContract;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WorkDay;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private readonly WorkDayService $workDayService) {}

    /**
     * Resumo de distribuição de tolerância (snapshot weekday_tolerance íntegro).
     *
     * Query: `year`+`month` ou `start_date`+`end_date`; `auditable_only=true` restringe a dias fechados com snapshot válido ({@see WorkDay::hasValidToleranceSnapshot()}).
     *
     * Cabeçalho `X-API-Version: 1` — formato estável deste endpoint para clientes.
     *
     * `meta.date_interpretation`: períodos são contextualizados no fuso da empresa quando configurado;
     * datas explícitas `start_date`/`end_date` seguem o calendário local ao período consultado (coluna `work_days.date`).
     *
     * `meta.reconciliation`: ordem e chaves estáveis — {@see ToleranceSummaryContract::RECONCILIATION_KEYS}.
     *
     * `meta.iterable_contains_non_workday`: true quando existem elementos que não são {@see WorkDay} no período.
     *
     * `total_rows_in_period` deve coincidir com `valid_work_day_rows` quando o iterável é só {@see WorkDay}.
     * Partição: `rows_without_snapshot` + `ignored_legacy_days` + `excluded_open_days` + `non_classified_days` + `considered_days`
     * (`excluded_by_auditable_only` espelha `excluded_open_days`).
     *
     * `meta.reconciliation_complete`: sem dados estranhos no iterável e identidade da soma.
     *
     * `meta.reconciliation_mismatch` (só quando incompleto): inclui `delta` = `expected - actual` (total menos soma das rubricas).
     */
    public function toleranceSummary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'auditable_only' => 'nullable|boolean',
        ]);

        $hasYear = $request->filled('year');
        $hasMonth = $request->filled('month');
        $hasStart = $request->filled('start_date');
        $hasEnd = $request->filled('end_date');

        if ($hasYear xor $hasMonth) {
            throw ValidationException::withMessages([
                'year' => ['Informe year e month em conjunto.'],
                'month' => ['Informe year e month em conjunto.'],
            ]);
        }

        if ($hasStart xor $hasEnd) {
            throw ValidationException::withMessages([
                'start_date' => ['Informe start_date e end_date em conjunto.'],
                'end_date' => ['Informe start_date e end_date em conjunto.'],
            ]);
        }

        $hasYm = $hasYear && $hasMonth;
        $hasRange = $hasStart && $hasEnd;

        if (! $hasYm && ! $hasRange) {
            throw ValidationException::withMessages([
                'period' => ['Informe year e month juntos ou start_date e end_date juntos.'],
            ]);
        }

        if ($hasYm && $hasRange) {
            throw ValidationException::withMessages([
                'period' => ['Use apenas um período: calendário (year/month) ou intervalo (start_date/end_date).'],
            ]);
        }

        $employeeId = $request->input('employee_id') ?? $request->user()->employee?->id;
        if ($employeeId === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['Sem colaborador vinculado ao utilizador; passe employee_id.'],
            ]);
        }

        $startedAt = microtime(true);

        $employee = Employee::with('company')->findOrFail($employeeId);
        $companyTzRaw = $employee->company?->timezone;
        $hasCompanyTz = is_string($companyTzRaw) && $companyTzRaw !== '';
        $tz = $hasCompanyTz ? $companyTzRaw : config('app.timezone', 'America/Sao_Paulo');
        $timezoneSource = $hasCompanyTz ? 'company' : 'app_default';
        $auditableOnly = $request->boolean('auditable_only');

        if ($hasYm) {
            $year = (int) $request->input('year');
            $month = (int) $request->input('month');
            $periodStart = Carbon::parse(sprintf('%04d-%02d-01', $year, $month), $tz)->startOfDay();
            $startDateStr = $periodStart->toDateString();
            $endDateStr = $periodStart->copy()->endOfMonth()->toDateString();

            $summary = $this->workDayService->getMonthSummary($employee, $year, $month);
            $workDays = $summary['work_days'];
        } else {
            $startDateStr = (string) $request->input('start_date');
            $endDateStr = (string) $request->input('end_date');

            $workDays = $employee->workDays()
                ->whereBetween('date', [$startDateStr, $endDateStr])
                ->orderBy('date')
                ->get();
        }

        $totalRows = $workDays->count();

        $block = WorkDay::aggregateToleranceUxSummary($workDays, $auditableOnly);

        $coveragePct = $totalRows === 0
            ? 0.0
            : min(100.0, round(100.0 * $block['meta']['considered_days'] / $totalRows, 1));

        $reconciliationSum =
            $block['meta']['rows_without_snapshot']
            + $block['meta']['ignored_legacy_days']
            + $block['meta']['non_classified_days']
            + $block['meta']['considered_days']
            + $block['meta']['excluded_open_days'];

        $validWd = $block['meta']['valid_work_day_rows'];

        $iterableContainsNonWorkday = $validWd !== $totalRows;

        $reconciliationComplete = ($validWd === $totalRows) && ($reconciliationSum === $validWd);

        $identityHolds = $reconciliationSum === $validWd;

        $reconciliation = ToleranceSummaryContract::orderedReconciliation([
            'total_rows_in_period' => $totalRows,
            'valid_work_day_rows' => $validWd,
            'rows_without_snapshot' => $block['meta']['rows_without_snapshot'],
            'ignored_legacy_days' => $block['meta']['ignored_legacy_days'],
            'excluded_open_days' => $block['meta']['excluded_open_days'],
            'non_classified_days' => $block['meta']['non_classified_days'],
            'considered_days' => $block['meta']['considered_days'],
            'excluded_by_auditable_only' => $block['meta']['excluded_by_auditable_only'],
            'sum_of_buckets' => $reconciliationSum,
            'identity_holds' => $identityHolds,
        ]);

        $meta = array_merge($block['meta'], [
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'timezone' => $tz,
            'timezone_source' => $timezoneSource,
            'date_interpretation' => 'company_timezone',
            'auditable_only' => $auditableOnly,
            'total_rows_in_period' => $totalRows,
            'coverage_pct' => $coveragePct,
            'iterable_contains_non_workday' => $iterableContainsNonWorkday,
            'reconciliation_complete' => $reconciliationComplete,
            'reconciliation' => $reconciliation,
        ]);

        if (! $reconciliationComplete) {
            $meta['reconciliation_mismatch'] = [
                'expected' => $totalRows,
                'actual' => $reconciliationSum,
                'valid_work_day_rows' => $validWd,
                'delta' => $totalRows - $reconciliationSum,
            ];
        }

        $logPayload = [
            'employee_id' => (int) $employeeId,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'timezone' => $tz,
            'timezone_source' => $timezoneSource,
            'rows_without_snapshot' => $block['meta']['rows_without_snapshot'],
            'considered_days' => $block['meta']['considered_days'],
            'ignored_legacy_days' => $block['meta']['ignored_legacy_days'],
            'non_classified_days' => $block['meta']['non_classified_days'],
            'excluded_open_days' => $block['meta']['excluded_open_days'],
            'excluded_by_auditable_only' => $block['meta']['excluded_by_auditable_only'],
            'valid_work_day_rows' => $validWd,
            'auditable_only' => $auditableOnly,
            'total_rows_in_period' => $totalRows,
            'iterable_contains_non_workday' => $iterableContainsNonWorkday,
            'reconciliation_sum' => $reconciliationSum,
            'reconciliation_complete' => $reconciliationComplete,
            'coverage_pct' => $coveragePct,
            'signature' => implode('|', [
                $block['meta']['considered_days'],
                $block['meta']['non_classified_days'],
                $block['meta']['ignored_legacy_days'],
                $block['meta']['excluded_open_days'],
                $block['meta']['rows_without_snapshot'],
            ]),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ];
        if (! $reconciliationComplete) {
            $logPayload['reconciliation_mismatch'] = $meta['reconciliation_mismatch'];
        }

        Log::info('tolerance_summary', $logPayload);

        return response()->json([
            'data' => $block['summary'],
            'meta' => $meta,
        ])->withHeaders([
            'X-API-Version' => '1',
        ]);
    }
}
