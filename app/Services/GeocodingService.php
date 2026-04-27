<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key', '');
    }

    /**
     * Geocodifica um endereço de texto e devolve latitude + longitude.
     * Devolve null se não conseguir resolver.
     *
     * @return array{lat: float, lng: float, formatted_address: string}|null
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('GeocodingService: GOOGLE_MAPS_KEY não configurada.');
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key'     => $this->apiKey,
            ]);

            if (! $response->ok()) {
                Log::warning('GeocodingService: HTTP ' . $response->status());
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
                Log::info('GeocodingService: status=' . ($data['status'] ?? 'n/a') . ' para "' . $address . '"');
                return null;
            }

            $location = $data['results'][0]['geometry']['location'];

            return [
                'lat'               => (float) $location['lat'],
                'lng'               => (float) $location['lng'],
                'formatted_address' => $data['results'][0]['formatted_address'] ?? $address,
            ];
        } catch (\Throwable $e) {
            Log::error('GeocodingService exception: ' . $e->getMessage());
            return null;
        }
    }
}
