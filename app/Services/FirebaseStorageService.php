<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class FirebaseStorageService
{
    private ?string $accessToken = null;

    public function __construct() {}

    private function getBucketName(): string
    {
        return config('firebase.projects.app.storage.default_bucket');
    }

    /**
     * Gera um access token OAuth2 via JWT do service account.
     * Usa scope cloud-platform que contorna as Firebase Security Rules
     * e acessa o GCS diretamente com privilégios de admin.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $credPath = base_path(config('firebase.projects.app.credentials'));
        $creds = json_decode(file_get_contents($credPath), true);

        $now = time();
        $payload = [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/devstorage.full_control',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $jwt = $this->buildJwt($payload, $creds['private_key']);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Falha ao autenticar com o Firebase: ' . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    private function buildJwt(array $payload, string $privateKey): string
    {
        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $body    = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signing = "{$header}.{$body}";

        openssl_sign($signing, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return "{$signing}.{$sig}";
    }

    public function uploadTimeRecordPhoto(
        UploadedFile $file,
        int $employeeId,
        string $type
    ): string {
        $path = sprintf(
            'time-records/%d/%s/%s_%s.%s',
            $employeeId,
            now()->format('Y/m/d'),
            $type,
            now()->format('His'),
            $file->getClientOriginalExtension()
        );

        $bucket = $this->getBucketName();
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['Content-Type' => $file->getMimeType()])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
            ->post("https://storage.googleapis.com/upload/storage/v1/b/{$bucket}/o?uploadType=media&name=" . rawurlencode($path));

        if ($response->failed()) {
            throw new \RuntimeException('Falha ao fazer upload para o Firebase Storage: ' . $response->body());
        }

        return $this->getPublicUrl($path);
    }

    public function generateSignedUploadUrl(int $employeeId, string $type, string $extension): array
    {
        $path = sprintf(
            'time-records/%d/%s/%s_%s.%s',
            $employeeId,
            now()->format('Y/m/d'),
            $type,
            now()->format('His'),
            $extension
        );

        // Retorna token de curta duração para o Flutter fazer upload diretamente
        return [
            'upload_url' => "https://storage.googleapis.com/upload/storage/v1/b/{$this->getBucketName()}/o?uploadType=media&name=" . rawurlencode($path),
            'path'       => $path,
            'public_url' => $this->getPublicUrl($path),
            'token'      => $this->getAccessToken(),
            'expires_at' => now()->addHour()->toISOString(),
        ];
    }

    public function getPublicUrl(string $path): string
    {
        $bucket = $this->getBucketName();
        return "https://storage.googleapis.com/{$bucket}/{$path}";
    }

    public function delete(string $path): void
    {
        try {
            $bucket = $this->getBucketName();
            Http::withToken($this->getAccessToken())
                ->delete("https://storage.googleapis.com/storage/v1/b/{$bucket}/o/" . rawurlencode($path));
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
