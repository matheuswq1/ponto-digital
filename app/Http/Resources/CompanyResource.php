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
            // Geocercas multi-endereço (nova estrutura)
            'geofences' => $this->whenLoaded('activeLocations', fn () =>
                $this->activeLocations->map(fn ($loc) => [
                    'id'            => $loc->id,
                    'name'          => $loc->name,
                    'address'       => $loc->address,
                    'latitude'      => (float) $loc->latitude,
                    'longitude'     => (float) $loc->longitude,
                    'radius_meters' => $loc->radius_meters,
                    'active'        => $loc->active,
                ])
            ),
            // Retro-compatibilidade (campo legado único)
            'geofence' => [
                'latitude'      => $this->latitude,
                'longitude'     => $this->longitude,
                'radius_meters' => $this->geofence_radius,
                'enabled'       => $this->hasLegacyGeofence(),
            ],
            'settings' => [
                'require_photo'           => $this->require_photo,
                'require_geolocation'     => $this->require_geolocation,
                'work_start'              => $this->work_start,
                'work_end'                => $this->work_end,
                'lunch_duration_minutes'  => $this->lunch_duration,
                'block_mock_location'     => $this->block_mock_location,
                'require_wifi'            => $this->require_wifi,
                'allowed_wifi_ssids'      => $this->allowed_wifi_ssids ?? [],
                'fraud_action'            => $this->fraud_action ?? 'warn',
            ],
            'active_employees_count' => $this->whenCounted('activeEmployees'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
