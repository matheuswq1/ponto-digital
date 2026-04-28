<?php

namespace App\Jobs;

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
 * Roda 30 min após o horário de saída e verifica se o colaborador
 * ainda está trabalhando (bateu entrada mas ainda não bateu saída).
 */
class CheckOvertimeAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(PushNotificationService $push): void
    {
        $tz  = config('app.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);
        $todayDow = (int) $now->format('N') % 7;

        $startOfDay = Carbon::now($tz)->startOfDay();
        $endOfDay   = Carbon::now($tz)->endOfDay();

        WorkSchedule::where('active', true)
            ->where('notify_overtime', true)
            ->with('employee.user')
            ->get()
            ->each(function (WorkSchedule $ws) use ($now, $todayDow, $tz, $startOfDay, $endOfDay, $push) {
                if (! $ws->isWorkDay($todayDow)) {
                    return;
                }

                $employee = $ws->employee;
                if (! $employee || ! $employee->active) {
                    return;
                }

                [$h, $m] = explode(':', $ws->exit_time);
                $expectedExit = Carbon::now($tz)->setTime((int)$h, (int)$m, 0);
                $cutoff = $expectedExit->copy()->addMinutes(30);

                if ($now->lt($cutoff)) {
                    return;
                }

                $records = $employee->timeRecords()
                    ->whereBetween('datetime', [$startOfDay, $endOfDay])
                    ->orderBy('datetime')
                    ->get();

                $lastRecord = $records->last();

                if ($lastRecord && $lastRecord->type === 'entrada') {
                    $minutesOver = (int) $expectedExit->diffInMinutes($now);
                    $push->sendToEmployee($employee, [
                        'title' => 'Hora extra acumulando ⏱️',
                        'body'  => "Você já ultrapassou {$minutesOver} min do horário de saída ({$ws->exit_time}). Não esqueça de bater a saída!",
                        'data'  => ['type' => 'overtime_alert', 'employee_id' => $employee->id],
                    ]);
                    Log::info('CheckOvertimeAlert: alerta enviado', ['employee_id' => $employee->id]);
                }
            });
    }
}
