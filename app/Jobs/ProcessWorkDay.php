<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\WorkDayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWorkDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly Employee $employee,
        private readonly string $date,
    ) {}

    public function handle(WorkDayService $workDayService): void
    {
        $workDayService->calculateAndSave($this->employee, $this->date);
    }

    public function tags(): array
    {
        return [
            'work-day',
            "employee:{$this->employee->id}",
            "date:{$this->date}",
        ];
    }
}
