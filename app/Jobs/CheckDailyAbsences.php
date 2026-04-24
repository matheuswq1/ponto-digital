<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Roda no fim do dia (ex: 23:00) e notifica quem não bateu
 * nenhum ponto no dia sendo um dia de trabalho.
 */
class CheckDailyAbsences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(PushNotificationService $push): void
    {
        $tz  = config('app.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);
        $todayDow = (int) $now->format('N') % 7;

        $startOfDay = Carbon::now($tz)->startOfDay()->utc();
        $endOfDay   = Carbon::now($tz)->endOfDay()->utc();

        WorkSchedule::where('active', true)
            ->where('notify_absence', true)
            ->with('employee.user')
            ->get()
            ->each(function (WorkSchedule $ws) use ($todayDow, $startOfDay, $endOfDay, $push) {
                if (! $ws->isWorkDay($todayDow)) {
                    return;
                }

                $employee = $ws->employee;
                if (! $employee || ! $employee->active) {
                    return;
                }

                $hasAnyRecord = $employee->timeRecords()
                    ->whereBetween('datetime', [$startOfDay, $endOfDay])
                    ->exists();

                if (! $hasAnyRecord) {
                    $push->sendToEmployee($employee, [
                        'title' => 'Ausência registrada 📋',
                        'body'  => 'Nenhum ponto foi registrado hoje. Entre em contato com o RH se precisar de ajuda.',
                        'data'  => ['type' => 'daily_absence', 'employee_id' => $employee->id],
                    ]);
                    Log::info('CheckDailyAbsences: alerta enviado', ['employee_id' => $employee->id]);
                }
            });
    }
}
