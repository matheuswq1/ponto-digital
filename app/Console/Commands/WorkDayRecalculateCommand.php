<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\WorkDay;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class WorkDayRecalculateCommand extends Command
{
    protected $signature = 'workday:recalculate
                            {--from= : Data inicial (Y-m-d)}
                            {--to= : Data final (Y-m-d)}
                            {--only-closed : Apenas dias com saída registada (is_closed)}
                            {--employee= : ID do colaborador (opcional)}
                            {--company= : ID da empresa (opcional)}
                            {--force-refresh-snapshot : Sobrescreve tolerance_snapshot em dias já fechados (homologação / migração de modo)}
                            {--chunk=500 : Registos por chunk}
                            {--dry-run : Listar quantidade sem gravar}';

    protected $description = 'Recalcula WorkDay(s) num intervalo. Por defeito mantém snapshot em dias fechados com auditoria válida; use --force-refresh-snapshot para alinhar histórico à regra atual.';

    public function handle(WorkDayService $workDayService): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            $this->error('Defina --from e --to (Y-m-d).');

            return self::FAILURE;
        }

        try {
            $fromDate = Carbon::parse($from)->toDateString();
            $toDate = Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $this->error('Datas inválidas. Use Y-m-d.');

            return self::FAILURE;
        }

        if ($fromDate > $toDate) {
            $this->error('--from não pode ser posterior a --to.');

            return self::FAILURE;
        }

        $onlyClosed = (bool) $this->option('only-closed');
        $employeeId = $this->option('employee');
        $employeeId = $employeeId !== null && $employeeId !== '' ? (int) $employeeId : null;
        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;
        $forceRefreshSnapshot = (bool) $this->option('force-refresh-snapshot');
        $preserveClosedToleranceSnapshot = ! $forceRefreshSnapshot;
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        // SQLite + cast `date`: whereBetween no campo pode ignorar linhas — usar whereDate (intervalo inclusivo).
        $baseQuery = WorkDay::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->when($onlyClosed, fn ($q) => $q->where('is_closed', true))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->orderBy('id');

        $totalRows = (clone $baseQuery)->count();

        if ($dryRun) {
            $this->info("[dry-run] {$totalRows} linha(s) em work_days corresponderiam ao filtro.");

            return self::SUCCESS;
        }

        $scopeNote = ($onlyClosed ? ' (só fechados)' : '')
            .($companyId ? " empresa #{$companyId}" : '')
            .($forceRefreshSnapshot ? '; snapshots fechados serão regravados' : '; snapshots fechados preservados por defeito');
        $this->info("Recálculo de {$totalRows} dia(s) entre {$fromDate} e {$toDate}{$scopeNote}…");

        $processed = 0;
        $errors = 0;

        $baseQuery->chunkById($chunk, function ($rows) use (
            $workDayService,
            &$processed,
            &$errors,
            $preserveClosedToleranceSnapshot,
            $forceRefreshSnapshot,
        ): void {
            foreach ($rows as $workDay) {
                /** @var WorkDay $workDay */
                try {
                    $employee = Employee::query()->find($workDay->employee_id);
                    if (! $employee) {
                        $errors++;
                        $this->warn("Employee #{$workDay->employee_id} inexistente — WorkDay #{$workDay->id} ignorado.");

                        continue;
                    }

                    $dateStr = $workDay->date instanceof CarbonInterface
                        ? $workDay->date->format('Y-m-d')
                        : (string) $workDay->date;

                    $recalculationAudit = $forceRefreshSnapshot ? [
                        'recalculated_at' => now()->utc()->toIso8601String(),
                        'recalculated_via' => 'cli:workday:recalculate',
                        'recalculated_from_engine' => data_get($workDay->tolerance_snapshot, 'engine'),
                    ] : null;

                    $workDayService->calculateAndSave($employee, $dateStr, $preserveClosedToleranceSnapshot, $recalculationAudit);
                    $processed++;
                    if ($processed % 500 === 0) {
                        $this->line("… {$processed} processados");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("WorkDay #{$workDay->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("Concluído: {$processed} recalculado(s)".($errors > 0 ? "; {$errors} erro(s)" : '').'.');

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
