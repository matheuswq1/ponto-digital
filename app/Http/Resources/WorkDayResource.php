<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'date_formatted' => $this->date?->format('d/m/Y'),
            'week_day' => $this->date?->locale('pt_BR')->isoFormat('dddd'),
            'times' => [
                'entry' => $this->entry_time,
                'lunch_start' => $this->lunch_start,
                'lunch_end' => $this->lunch_end,
                'exit' => $this->exit_time,
            ],
            'minutes' => [
                'total' => $this->total_minutes,
                'expected' => $this->expected_minutes,
                'extra' => $this->extra_minutes,
                'lunch' => $this->lunch_minutes,
            ],
            'hours' => [
                'total' => $this->formatted_total,
                'extra' => $this->formatted_extra,
            ],
            'status' => $this->status,
            'observations' => $this->observations,
            'is_closed' => $this->is_closed,
            'balance_type' => $this->extra_minutes > 0 ? 'positivo' : ($this->extra_minutes < 0 ? 'negativo' : 'neutro'),
        ];
    }
}
