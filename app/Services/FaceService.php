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
        $response = $this->http()
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
        $response = $this->http()
            ->attach('photo', fopen($photoPath, 'r'), basename($photoPath))
            ->post("{$this->baseUrl}/verify", [
                'employee_id' => (string) $employeeId,
            ]);

        return $this->handleResponse($response, 'verify');
    }

    /**
     * Identifica a pessoa na foto comparando contra os embeddings de uma lista de funcionários.
     * O serviço de IA recebe a lista de employee_ids e retorna o melhor match.
     *
     * @param  array<int|string>  $employeeIds
     * @return array{match: bool, employee_id: int|null, score: float, distance: float, threshold: float}
     */
    public function identify(array $employeeIds, string $photoPath): array
    {
        $response = $this->http()
            ->attach('photo', fopen($photoPath, 'r'), basename($photoPath))
            ->post("{$this->baseUrl}/identify", [
                'employee_ids' => implode(',', $employeeIds),
            ]);

        return $this->handleResponse($response, 'identify');
    }

    /**
     * Remove o embedding de um colaborador.
     */
    public function deleteEnrollment(int|string $employeeId): array
    {
        $response = $this->http()
            ->delete("{$this->baseUrl}/enroll/{$employeeId}");

        return $this->handleResponse($response, 'delete');
    }

    /**
     * Cliente HTTP com limites explícitos (evita workers PHP presos; ML pode demorar dezenas de segundos).
     */
    private function http()
    {
        $timeout = (int) config('services.face_service.timeout', 50);
        $connect = (int) config('services.face_service.connect_timeout', 5);

        return Http::withHeaders(['X-Face-Service-Key' => $this->apiKey])
            ->connectTimeout($connect)
            ->timeout($timeout);
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
