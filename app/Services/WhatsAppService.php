<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integração com a API WhatsApp própria (whats.approsamistica.com).
 *
 * Configuração (.env):
 *   WHATSAPP_ENABLED=true
 *   WHATSAPP_API_URL=https://whats.approsamistica.com/api/integration
 *   WHATSAPP_API_KEY=sk_af7b179fc3df8d5e0dac319c976f1450817ca88af140bc7c
 */
class WhatsAppService
{
    private string $baseUrl;
    private string $apiKey;
    private bool   $enabled;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.whatsapp.url', ''), '/');
        $this->apiKey   = config('services.whatsapp.api_key', '');
        $this->enabled  = (bool) config('services.whatsapp.enabled', false);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Mensagens pré-formatadas para notificações de RH
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Notifica o RH sobre uma nova solicitação de correção de ponto.
     */
    public function notifyEditRequest(
        string $toNumber,
        string $employeeName,
        string $companyName,
        string $requestedDate,
        string $reason,
        string $adminUrl
    ): bool {
        $message = "🕐 *Nova Solicitação de Correção de Ponto*\n\n"
            . "👤 *Colaborador:* {$employeeName}\n"
            . "🏢 *Empresa:* {$companyName}\n"
            . "📅 *Data/Hora:* {$requestedDate}\n"
            . "📝 *Motivo:* {$reason}\n\n"
            . "Acesse o painel para aprovar ou rejeitar:\n{$adminUrl}";

        return $this->send($toNumber, $message, 'correcao-ponto');
    }

    /**
     * Notifica o RH sobre uma nova solicitação de adição de registro.
     */
    public function notifyAdditionRequest(
        string $toNumber,
        string $employeeName,
        string $companyName,
        string $requestedDate,
        string $requestedTime,
        string $reason,
        string $adminUrl
    ): bool {
        $message = "➕ *Nova Solicitação de Adição de Ponto*\n\n"
            . "👤 *Colaborador:* {$employeeName}\n"
            . "🏢 *Empresa:* {$companyName}\n"
            . "📅 *Data:* {$requestedDate}\n"
            . "⏰ *Horário solicitado:* {$requestedTime}\n"
            . "📝 *Motivo:* {$reason}\n\n"
            . "Acesse o painel para aprovar ou rejeitar:\n{$adminUrl}";

        return $this->send($toNumber, $message, 'adicao-ponto');
    }

    // ────────────────────────────────────────────────────────────────────────
    // Envio genérico de mensagem de texto
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Envia uma mensagem de texto via WhatsApp.
     *
     * @param  string  $to          Número destino (somente dígitos, com DDI — ex.: 5511999999999)
     * @param  string  $message     Texto da mensagem
     * @param  string|null $ref     Referência opcional salva nos logs da API
     */
    public function send(string $to, string $message, ?string $ref = null): bool
    {
        if (! $this->enabled) {
            Log::info('[WhatsApp] Serviço desabilitado (WHATSAPP_ENABLED=false). Para: '.$to);
            return false;
        }

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('[WhatsApp] API não configurada — verifique WHATSAPP_API_URL e WHATSAPP_API_KEY.');
            return false;
        }

        // Sanitiza o número: remove tudo que não for dígito
        $to = preg_replace('/\D/', '', $to);

        // Garante DDI Brasil se o número começar com 0 ou tiver apenas 10/11 dígitos
        if (strlen($to) <= 11 && ! str_starts_with($to, '55')) {
            $to = '55' . $to;
        }

        if (strlen($to) < 12) {
            Log::warning('[WhatsApp] Número inválido após sanitização: '.$to);
            return false;
        }

        $payload = [
            'to'      => $to,
            'type'    => 'text',
            'content' => $message,
        ];

        if ($ref) {
            $payload['externalRef'] = $ref;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post("{$this->baseUrl}/send", $payload);

            if ($response->successful()) {
                $logId = $response->json('logId');
                Log::info("[WhatsApp] Mensagem enfileirada para {$to}. logId={$logId}");
                return true;
            }

            Log::warning('[WhatsApp] Falha ao enviar. HTTP '.$response->status().' | '.$response->body());
            return false;

        } catch (\Throwable $e) {
            Log::error('[WhatsApp] Exceção: '.$e->getMessage());
            return false;
        }
    }
}
