<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Validation\ValidationException;

class GeolocationService
{
    /**
     * Valida se as coordenadas fornecidas estão dentro de PELO MENOS UMA
     * das geocercas activas da empresa.
     * Se a empresa não tiver nenhuma geocerca configurada, a validação passa.
     */
    public function validateGeofence(Company $company, float $lat, float $lng): void
    {
        $locations = $company->activeLocations()->get();

        // Retro-compatibilidade: se não houver locations novas, usar campos legado
        if ($locations->isEmpty()) {
            if (! $company->hasLegacyGeofence()) {
                return;
            }
            $distance = $this->calculateDistance(
                (float) $company->latitude,
                (float) $company->longitude,
                $lat,
                $lng
            );
            if ($distance > $company->geofence_radius) {
                throw ValidationException::withMessages([
                    'location' => [sprintf(
                        'Você está fora da área permitida. Distância atual: %dm. Permitido: %dm.',
                        (int) $distance,
                        $company->geofence_radius
                    )],
                ]);
            }
            return;
        }

        // Verificar se o utilizador está dentro de QUALQUER uma das locations
        foreach ($locations as $location) {
            $distance = $this->calculateDistance(
                (float) $location->latitude,
                (float) $location->longitude,
                $lat,
                $lng
            );
            if ($distance <= $location->radius_meters) {
                return; // Dentro de pelo menos uma geocerca — OK
            }
        }

        // Fora de todas as geocercas
        $names = $locations->pluck('name')->implode(', ');
        throw ValidationException::withMessages([
            'location' => ["Você está fora de todas as áreas permitidas ({$names})."],
        ]);
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
        $locations = $company->activeLocations()->get();

        if ($locations->isEmpty()) {
            if (! $company->hasLegacyGeofence()) {
                return true;
            }
            $distance = $this->calculateDistance(
                (float) $company->latitude,
                (float) $company->longitude,
                $lat,
                $lng
            );
            return $distance <= $company->geofence_radius;
        }

        foreach ($locations as $location) {
            $distance = $this->calculateDistance(
                (float) $location->latitude,
                (float) $location->longitude,
                $lat,
                $lng
            );
            if ($distance <= $location->radius_meters) {
                return true;
            }
        }

        return false;
    }
}
