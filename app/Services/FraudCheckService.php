<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FraudAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resultado da verificação anti-fraude.
 */
class FraudCheckResult
{
    /** @param string[] $violations */
    public function __construct(
        public readonly bool  $blocked,
        public readonly array $violations,
        /** @var FraudAttempt[] */
        public readonly array $attempts,
    ) {}
}

class FraudCheckService
{
    /**
     * Avalia todas as regras de fraude configuradas para a empresa do colaborador.
     * Cria registos FraudAttempt para cada violação e retorna o resultado.
     */
    public function check(Employee $employee, array $data): FraudCheckResult
    {
        $employee->loadMissing('company');
        $company = $employee->company;

        if (! $company) {
            return new FraudCheckResult(false, [], []);
        }

        $violations = [];
        $attempts   = [];

        $ip       = $data['ip_address'] ?? request()->ip();
        $lat      = isset($data['latitude'])  ? (float) $data['latitude']  : null;
        $lon      = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $deviceId = $data['device_id'] ?? null;

        // ── 1. GPS Falso (mock location) ──────────────────────────────────
        if ($company->block_mock_location && ! empty($data['is_mock_location'])) {
            $attempt = $this->record($employee, $company, 'mock_location', [
                'is_mock_location' => true,
                'device_id'        => $deviceId,
            ], $lat, $lon, $deviceId, $ip, $company->fraud_action);
            $violations[] = 'GPS falso detectado';
            $attempts[]   = $attempt;
        }

        // ── 2. Salto de velocidade impossível ─────────────────────────────
        if ($company->block_velocity_jump && $lat !== null && $lon !== null) {
            $jump = $this->checkVelocityJump($employee, $lat, $lon, $company->velocity_jump_threshold_kmh);
            if ($jump !== null) {
                $attempt = $this->record($employee, $company, 'velocity_jump', $jump, $lat, $lon, $deviceId, $ip, $company->fraud_action);
                $violations[] = 'Salto de localização suspeito (' . round($jump['speed_kmh']) . ' km/h)';
                $attempts[]   = $attempt;
            }
        }

        // ── 3. Wi-Fi não autorizado ───────────────────────────────────────
        if ($company->require_wifi) {
            $sentSsid    = $data['wifi_ssid'] ?? null;
            $allowed     = (array) ($company->allowed_wifi_ssids ?? []);
            $ssidPresent = $sentSsid !== null && $sentSsid !== '';
            $ssidAllowed = $ssidPresent && count($allowed) > 0
                ? in_array(trim($sentSsid), array_map('trim', $allowed), true)
                : false;

            if (! $ssidAllowed) {
                $attempt = $this->record($employee, $company, 'wifi_mismatch', [
                    'wifi_ssid_sent'  => $sentSsid,
                    'allowed_ssids'   => $allowed,
                ], $lat, $lon, $deviceId, $ip, $company->fraud_action);
                $violations[] = 'Wi-Fi não autorizado' . ($sentSsid ? ' (' . $sentSsid . ')' : ' (desconhecido)');
                $attempts[]   = $attempt;
            }
        }

        // ── 4. Cidade do IP divergente ────────────────────────────────────
        if ($company->block_unknown_ip_city && $ip) {
            $cityInfo = $this->resolveIpCity($ip);
            if ($cityInfo && $company->city) {
                $cityNorm    = mb_strtolower(trim($company->city));
                $detectedNorm = mb_strtolower(trim($cityInfo['city'] ?? ''));
                if ($detectedNorm && $detectedNorm !== $cityNorm) {
                    $attempt = $this->record($employee, $company, 'ip_city_mismatch', [
                        'ip'             => $ip,
                        'detected_city'  => $cityInfo['city'],
                        'detected_region'=> $cityInfo['region'] ?? null,
                        'company_city'   => $company->city,
                    ], $lat, $lon, $deviceId, $ip, $company->fraud_action);
                    $violations[] = 'IP de cidade divergente (' . $cityInfo['city'] . ')';
                    $attempts[]   = $attempt;
                }
            }
        }

        $blocked = count($violations) > 0 && $company->fraud_action === 'block';

        return new FraudCheckResult($blocked, $violations, $attempts);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Calcula a velocidade entre o último ponto registado e o atual.
     * Devolve array com details se a velocidade ultrapassar o threshold, ou null.
     */
    private function checkVelocityJump(Employee $employee, float $lat, float $lon, int $thresholdKmh): ?array
    {
        $last = $employee->timeRecords()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('datetime')
            ->first(['datetime', 'latitude', 'longitude']);

        if (! $last) {
            return null;
        }

        $distanceKm = $this->haversineKm(
            (float) $last->latitude,
            (float) $last->longitude,
            $lat,
            $lon
        );

        $seconds = max(1, Carbon::now('UTC')->diffInSeconds($last->datetime));
        $speedKmh = $distanceKm / ($seconds / 3600);

        if ($speedKmh > $thresholdKmh) {
            return [
                'speed_kmh'           => round($speedKmh, 1),
                'threshold_kmh'       => $thresholdKmh,
                'distance_km'         => round($distanceKm, 2),
                'elapsed_seconds'     => $seconds,
                'previous_lat'        => (float) $last->latitude,
                'previous_lon'        => (float) $last->longitude,
                'previous_datetime'   => $last->datetime->toIso8601String(),
            ];
        }

        return null;
    }

    /**
     * Distância em km usando a fórmula de Haversine.
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * asin(sqrt($a));
    }

    /**
     * Resolve a cidade de um IP usando ip-api.com (gratuito, sem chave necessária).
     * Retorna null em caso de falha para não bloquear o fluxo.
     */
    private function resolveIpCity(string $ip): ?array
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return null;
        }
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName");
            if ($response->ok() && $response->json('status') === 'success') {
                return [
                    'city'   => $response->json('city'),
                    'region' => $response->json('regionName'),
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('FraudCheckService IP lookup failed: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Cria um FraudAttempt no banco.
     */
    private function record(
        Employee $employee,
        Company  $company,
        string   $rule,
        array    $details,
        ?float   $lat,
        ?float   $lon,
        ?string  $deviceId,
        ?string  $ip,
        string   $fraudAction,
    ): FraudAttempt {
        $action = $fraudAction === 'block' ? 'blocked' : 'warned';

        return FraudAttempt::create([
            'employee_id'   => $employee->id,
            'company_id'    => $company->id,
            'rule_triggered'=> $rule,
            'details'       => $details,
            'latitude'      => $lat,
            'longitude'     => $lon,
            'device_id'     => $deviceId,
            'ip_address'    => $ip,
            'action_taken'  => $action,
        ]);
    }
}
