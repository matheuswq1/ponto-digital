<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
