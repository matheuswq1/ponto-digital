<?php

use App\Jobs\CheckDailyAbsences;
use App\Jobs\CheckLateArrivals;
use App\Jobs\CheckOvertimeAlert;
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

// Alerta de atraso — roda a cada 15 min entre 06:00 e 11:00
// O job filtra apenas quem passou do horário+tolerância sem bater entrada
Schedule::job(new CheckLateArrivals())
    ->everyFifteenMinutes()
    ->between('06:00', '11:00')
    ->name('check-late-arrivals')
    ->withoutOverlapping();

// Alerta de ausência total — roda às 20:00 (fim do expediente)
Schedule::job(new CheckDailyAbsences())
    ->dailyAt('20:00')
    ->name('check-daily-absences')
    ->withoutOverlapping();

// Alerta de hora extra — roda a cada 30 min entre 17:00 e 22:00
Schedule::job(new CheckOvertimeAlert())
    ->everyThirtyMinutes()
    ->between('17:00', '22:00')
    ->name('check-overtime-alert')
    ->withoutOverlapping();
