<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'entry_time'        => $this->entry_time,
            'exit_time'         => $this->exit_time,
            'lunch_minutes'     => $this->lunch_minutes, // null = sem intervalo fixo
            'tolerance_minutes' => $this->tolerance_minutes,
            'work_days'         => $this->work_days,
            'expected_minutes'  => $this->getExpectedMinutes(),
            'active'            => $this->active,
        ];
    }
}
