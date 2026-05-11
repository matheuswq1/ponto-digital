<?php

namespace App\Services;

use App\Http\Resources\WorkDayResource;
use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Models\TimeRecord;
use App\Models\WorkDay;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Monta o mesmo pacote de dados do espelho que a API expõe em mine-detail (para hash probatório).
 */
class PayPeriodMirrorPayloadService
{
    public function __construct(
        private readonly WorkDayService $workDayService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildMirrorPayload(
        Employee $employee,
        PayPeriodClosure $closure,
        EmployeePayPeriodAcknowledgement $ack,
        Request $request,
    ): array {
        $start = $closure->period_start->toDateString();
        $end = $closure->period_end->toDateString();

        $balance = $this->workDayService->getPeriodBalance($employee, $start, $end);

        $workDays = $employee->workDays()
            ->whereBetween('date', [$start, $end])
            ->with('employee:id,company_id')
            ->orderBy('date')
            ->get();

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $records = $employee->timeRecords()
            ->where('datetime', '>=', Carbon::parse($start, $tz)->startOfDay())
            ->where('datetime', '<=', Carbon::parse($end, $tz)->endOfDay())
            ->orderBy('datetime')
            ->get();

        $recordsByDate = $records->groupBy(fn (TimeRecord $r) => $r->datetime->format('Y-m-d'));

        $workDaysPayload = $workDays->map(function (WorkDay $wd) use ($recordsByDate, $request) {
            $base = (new WorkDayResource($wd))->toArray($request);
            $key = $wd->date->toDateString();
            $dayRecords = $recordsByDate->get($key, collect());
            $base['time_records'] = $dayRecords->map(fn (TimeRecord $tr) => [
                'id' => $tr->id,
                'type' => $tr->type,
                'type_label' => $tr->getTypeLabel(),
                'time' => $tr->datetime->format('H:i'),
                'datetime' => $tr->datetime->format('Y-m-d\TH:i:s'),
            ])->values()->all();

            return $base;
        })->values()->all();

        return [
            'acknowledgement' => [
                'id' => $ack->id,
                'status' => $ack->status,
                'employee_notes' => $ack->employee_notes,
                'responded_at' => $ack->responded_at?->toIso8601String(),
            ],
            'closure' => [
                'id' => $closure->id,
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $closure->notes,
                'is_correction' => $closure->corrected_from_closure_id !== null,
                'closed_at' => $closure->closed_at->toIso8601String(),
            ],
            'summary' => [
                'total_worked_minutes' => $balance['total_worked_minutes'],
                'total_expected_minutes' => $balance['total_expected_minutes'],
                'balance_minutes' => $balance['balance_minutes'],
                'days_worked' => $balance['days_worked'],
                'days_absent' => $balance['days_absent'],
                'balance_hours' => $this->formatSignedHours((int) $balance['balance_minutes']),
                'worked_hours' => $this->formatUnsignedHours((int) $balance['total_worked_minutes']),
                'expected_hours' => $this->formatUnsignedHours((int) $balance['total_expected_minutes']),
            ],
            'work_days' => $workDaysPayload,
        ];
    }

    private function formatUnsignedHours(int $minutes): string
    {
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    private function formatSignedHours(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }
        $sign = $minutes > 0 ? '+' : '−';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $h, $m);
    }
}
