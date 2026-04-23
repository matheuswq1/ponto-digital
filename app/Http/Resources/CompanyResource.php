<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'logo_url' => $this->logo_url,
            'active' => $this->active,
            'geofence' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'radius_meters' => $this->geofence_radius,
                'enabled' => $this->hasGeofence(),
            ],
            'settings' => [
                'require_photo' => $this->require_photo,
                'require_geolocation' => $this->require_geolocation,
                'work_start' => $this->work_start,
                'work_end' => $this->work_end,
                'lunch_duration_minutes' => $this->lunch_duration,
            ],
            'active_employees_count' => $this->whenCounted('activeEmployees'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
