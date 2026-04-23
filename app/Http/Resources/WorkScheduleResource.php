<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'entry_time' => $this->entry_time,
            'lunch_start' => $this->lunch_start,
            'lunch_end' => $this->lunch_end,
            'exit_time' => $this->exit_time,
            'tolerance_minutes' => $this->tolerance_minutes,
            'work_days' => $this->work_days,
            'expected_minutes' => $this->getExpectedMinutes(),
            'active' => $this->active,
        ];
    }
}
