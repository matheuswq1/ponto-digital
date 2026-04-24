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
 * Dispara após o horário de entrada (+tolerância) e verifica
 * quem ainda não bateu o ponto de entrada no dia.
 */
class CheckLateArrivals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(PushNotificationService $push): void
    {
        $tz  = config('app.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);
        $todayDow = (int) $now->format('N') % 7; // 0=Dom … 6=Sáb

        WorkSchedule::where('active', true)
            ->where('notify_late', true)
            ->with('employee.user')
            ->get()
            ->each(function (WorkSchedule $ws) use ($now, $todayDow, $tz, $push) {
                if (! $ws->isWorkDay($todayDow)) {
                    return;
                }

                $employee = $ws->employee;
                if (! $employee || ! $employee->active) {
                    return;
                }

                [$h, $m] = explode(':', $ws->entry_time);
                $expectedEntry = Carbon::now($tz)->setTime((int)$h, (int)$m, 0);
                $cutoff = $expectedEntry->copy()->addMinutes($ws->tolerance_minutes);

                if ($now->lt($cutoff)) {
                    return;
                }

                $startOfDay = Carbon::now($tz)->startOfDay()->utc();
                $endOfDay   = Carbon::now($tz)->endOfDay()->utc();

                $hasEntry = $employee->timeRecords()
                    ->where('type', 'entrada')
                    ->whereBetween('datetime', [$startOfDay, $endOfDay])
                    ->exists();

                if (! $hasEntry) {
                    $entryFormatted = $expectedEntry->format('H:i');
                    $push->sendToEmployee($employee, [
                        'title' => 'Atraso detectado ⚠️',
                        'body'  => "Seu horário de entrada era {$entryFormatted}. Não esqueça de registrar o ponto!",
                        'data'  => ['type' => 'late_arrival', 'employee_id' => $employee->id],
                    ]);
                    Log::info('CheckLateArrivals: alerta enviado', ['employee_id' => $employee->id]);
                }
            });
    }
}
