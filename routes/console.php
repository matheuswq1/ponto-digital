<?php

use App\Jobs\SyncPendingTimeRecords;
use App\Models\Company;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ponto:recalculate-month {year} {month} {--company=}', function (WorkDayService $service) {
    $year = (int) $this->argument('year');
    $month = (int) $this->argument('month');
    $companyId = $this->option('company');

    $query = Company::query();
    if ($companyId) {
        $query->where('id', $companyId);
    }

    $companies = $query->with('activeEmployees')->get();

    $this->info("Recalculando {$year}/{$month} para {$companies->count()} empresa(s)...");

    foreach ($companies as $company) {
        $this->line("  Empresa: {$company->name}");
        \App\Jobs\RecalculateMonthlyBalance::dispatchForCompany($company, $year, $month);
    }

    $this->info('Jobs despachados com sucesso!');
})->purpose('Recalcula o banco de horas de um mês');

Artisan::command('ponto:sync-offline', function () {
    SyncPendingTimeRecords::dispatch();
    $this->info('Job de sincronização offline despachado.');
})->purpose('Sincroniza registros offline pendentes');

// Agendamentos
Schedule::job(new SyncPendingTimeRecords())->hourly()->name('sync-offline-records');
