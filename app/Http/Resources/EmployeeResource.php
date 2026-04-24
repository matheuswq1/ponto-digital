<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'department_id' => $this->department_id,
            'cpf' => $this->cpf,
            'cargo' => $this->cargo,
            'department' => $this->dept?->name ?? $this->department,
            'registration_number' => $this->registration_number,
            'admission_date' => $this->admission_date?->toDateString(),
            'dismissal_date' => $this->dismissal_date?->toDateString(),
            'contract_type' => $this->contract_type,
            'weekly_hours' => $this->weekly_hours,
            'active' => $this->active,
            'photo_url' => $this->photo_url,
            'face_enrolled' => (bool) $this->face_enrolled,
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'company' => $this->whenLoaded('company', fn() => new CompanyResource($this->company)),
            'work_schedule' => $this->whenLoaded('workSchedule', fn() => new WorkScheduleResource($this->workSchedule)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
