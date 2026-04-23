<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class GeolocationService
{
    public function validateGeofence(Company $company, float $lat, float $lng): void
    {
        if (!$company->hasGeofence()) {
            return;
        }

        $distance = $this->calculateDistance(
            $company->latitude,
            $company->longitude,
            $lat,
            $lng
        );

        if ($distance > $company->geofence_radius) {
            throw ValidationException::withMessages([
                'location' => [
                    sprintf(
                        'Você está fora da área permitida. Distância atual: %dm. Permitido: %dm.',
                        (int) $distance,
                        $company->geofence_radius
                    )
                ]
            ]);
        }
    }

    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function isWithinGeofence(Company $company, float $lat, float $lng): bool
    {
        if (!$company->hasGeofence()) {
            return true;
        }

        $distance = $this->calculateDistance(
            $company->latitude,
            $company->longitude,
            $lat,
            $lng
        );

        return $distance <= $company->geofence_radius;
    }
}
