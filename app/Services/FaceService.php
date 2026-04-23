<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.face_service.url', 'http://localhost:8001'), '/');
        $this->apiKey  = config('services.face_service.key', '');
    }

    /**
     * Cadastra (ou actualiza) o rosto de um colaborador.
     *
     * @param  int|string  $employeeId
     * @param  string      $photoPath  Caminho absoluto para o ficheiro de imagem
     */
    public function enroll(int|string $employeeId, string $photoPath): array
    {
        $response = Http::withHeaders(['X-Face-Service-Key' => $this->apiKey])
            ->attach('photo', fopen($photoPath, 'r'), basename($photoPath))
            ->post("{$this->baseUrl}/enroll", [
                'employee_id' => (string) $employeeId,
            ]);

        return $this->handleResponse($response, 'enroll');
    }

    /**
     * Verifica se a foto corresponde ao rosto cadastrado.
     *
     * @return array{match: bool, score: float, distance: float, threshold: float}
     */
    public function verify(int|string $employeeId, string $photoPath): array
    {
        $response = Http::withHeaders(['X-Face-Service-Key' => $this->apiKey])
            ->attach('photo', fopen($photoPath, 'r'), basename($photoPath))
            ->post("{$this->baseUrl}/verify", [
                'employee_id' => (string) $employeeId,
            ]);

        return $this->handleResponse($response, 'verify');
    }

    /**
     * Remove o embedding de um colaborador.
     */
    public function deleteEnrollment(int|string $employeeId): array
    {
        $response = Http::withHeaders(['X-Face-Service-Key' => $this->apiKey])
            ->delete("{$this->baseUrl}/enroll/{$employeeId}");

        return $this->handleResponse($response, 'delete');
    }

    private function handleResponse(Response $response, string $op): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $status  = $response->status();
        $message = $response->json('detail') ?? $response->body();

        Log::warning("[FaceService] Operação '{$op}' falhou. HTTP {$status}: {$message}");

        throw new \RuntimeException($message, $status);
    }
}
