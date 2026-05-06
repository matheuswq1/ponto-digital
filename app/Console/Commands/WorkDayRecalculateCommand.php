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
                            {--chunk=500 : Registos por chunk}
                            {--dry-run : Listar quantidade sem gravar}';

    protected $description = 'Recalcula WorkDay(s) num intervalo (atualiza saldo e snapshot; use após migrações de regras)';

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
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = WorkDay::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->when($onlyClosed, fn ($q) => $q->where('is_closed', true))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('id');

        $totalRows = (clone $baseQuery)->count();

        if ($dryRun) {
            $this->info("[dry-run] {$totalRows} linha(s) em work_days corresponderiam ao filtro.");

            return self::SUCCESS;
        }

        $this->info("Recálculo de {$totalRows} dia(s) entre {$fromDate} e {$toDate}".($onlyClosed ? ' (só fechados)' : '').'…');

        $processed = 0;
        $errors = 0;

        $baseQuery->chunkById($chunk, function ($rows) use ($workDayService, &$processed, &$errors): void {
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

                    $workDayService->calculateAndSave($employee, $dateStr, false);
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
