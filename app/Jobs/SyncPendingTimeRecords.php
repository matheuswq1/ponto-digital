<?php

namespace App\Jobs;

use App\Models\TimeRecord;
use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPendingTimeRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WorkDayService $workDayService): void
    {
        // Recalcula dias que tiveram registros offline sincronizados nas últimas 24h
        $affectedDays = TimeRecord::where('offline', true)
            ->where('synced_at', '>=', Carbon::now()->subHours(24))
            ->select('employee_id', \Illuminate\Support\Facades\DB::raw('DATE(datetime) as record_date'))
            ->distinct()
            ->get();

        foreach ($affectedDays as $day) {
            $employee = \App\Models\Employee::find($day->employee_id);
            if ($employee) {
                $workDayService->calculateAndSave($employee, $day->record_date);
            }
        }
    }
}
