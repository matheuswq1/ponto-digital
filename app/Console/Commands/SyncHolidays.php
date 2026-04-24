<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\HolidayService;
use Illuminate\Console\Command;

class SyncHolidays extends Command
{
    protected $signature = 'ponto:sync-holidays
                            {year? : Ano a sincronizar (padrão: ano atual e próximo)}
                            {--company= : ID de empresa específica}';

    protected $description = 'Sincroniza feriados nacionais, estaduais e municipais (BrasilAPI + GitHub joaopbini/feriados-brasil)';

    public function handle(HolidayService $service): int
    {
        $yearArg   = $this->argument('year');
        $companyId = $this->option('company');
        $years     = $yearArg ? [(int) $yearArg] : [now()->year, now()->year + 1];

        $companies = Company::where('active', true)
            ->when($companyId, fn($q) => $q->where('id', $companyId))
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Nenhuma empresa ativa encontrada.');
            return self::SUCCESS;
        }

        foreach ($years as $year) {
            $this->info("=== Sincronizando feriados {$year} ===");
            $bar = $this->output->createProgressBar($companies->count());
            $bar->start();

            $total = 0;
            foreach ($companies as $company) {
                $count  = $service->syncForCompany($company, $year);
                $total += $count;

                $info = $company->name;
                if ($company->state)     $info .= " [{$company->state}]";
                if ($company->ibge_code) $info .= " IBGE:{$company->ibge_code}";
                $this->line("  {$info}: {$count} feriados");
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Total {$year}: {$total} feriados importados/atualizados.");
        }

        return self::SUCCESS;
    }
}
