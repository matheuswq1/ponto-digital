<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\FraudAttempt;
use App\Models\TimeRecordEdit;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class PushNotificationService
{
    public function notifyEditRequestResolved(TimeRecordEdit $edit, string $outcome, ?string $notes = null): void
    {
        $messaging = $this->messaging();
        if ($messaging === null) {
            Log::warning('PushNotificationService: FCM indisponível ao notificar correção de ponto');

            return;
        }

        $user = User::query()->find($edit->edited_by);
        if (! $user) {
            Log::warning('PushNotificationService: usuário edited_by não encontrado para edit '.$edit->id);

            return;
        }

        $title = match ($outcome) {
            'aprovado' => 'Correção de ponto aprovada',
            'rejeitado' => 'Correção de ponto rejeitada',
            default => 'Solicitação de correção',
        };
        $body = $outcome === 'aprovado'
            ? 'Sua correção de ponto foi aprovada.'
            : 'Sua correção de ponto foi rejeitada.';

        if ($notes) {
            $body .= ' '.mb_substr($notes, 0, 120);
        }

        $notification = Notification::create($title, $body);
        $dataPayload = $this->normalizeDataForFcm([
            'type' => $outcome === 'aprovado' ? 'edit_request_approved' : 'edit_request_rejected',
            'edit_id' => (string) $edit->id,
        ]);

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            Log::info("PushNotificationService: sem tokens FCM para user_id {$user->id} (correção de ponto)");

            return;
        }

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($dataPayload);
                $messaging->send($message);
            } catch (Throwable $e) {
                Log::warning('FCM: '.$e->getMessage());
            }
        }
    }

    /**
     * Envia push para todos os dispositivos registados de um utilizador.
     *
     * @param  array{title: string, body: string, data?: array<string, mixed>}  $payload
     */
    public function sendToUser(?User $user, array $payload): void
    {
        if ($user === null) {
            Log::warning('PushNotificationService: sendToUser com utilizador null');

            return;
        }

        $messaging = $this->messaging();
        if ($messaging === null) {
            Log::warning('PushNotificationService: FCM indisponível (sendToUser)');

            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            Log::info("PushNotificationService: sem tokens FCM para user_id {$user->id}");

            return;
        }

        $notification = Notification::create($payload['title'], $payload['body']);
        $data = $this->normalizeDataForFcm($payload['data'] ?? []);

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($data);
                $messaging->send($message);
            } catch (Throwable $e) {
                Log::warning('FCM sendToUser: '.$e->getMessage(), ['user_id' => $user->id, 'token' => substr($token, 0, 20)]);
            }
        }
    }

    /**
     * Envia push notification para todos os dispositivos de um colaborador.
     *
     * @param  Employee  $employee
     * @param  array{title: string, body: string, data?: array<string,mixed>}  $payload
     */
    public function sendToEmployee(Employee $employee, array $payload): void
    {
        $user = User::query()->find($employee->user_id);
        if ($user === null) {
            Log::warning("PushNotificationService: employee {$employee->id} sem utilizador válido (user_id {$employee->user_id})");

            return;
        }

        $this->sendToUser($user, $payload);
    }

    /**
     * Envia uma notificação para vários utilizadores (tokens únicos).
     *
     * @param  array<int>  $userIds
     * @param  array<string, mixed>  $data  Tipagem livre; valores são normalizados para string na FCM.
     */
    public function sendToUserIds(array $userIds, string $title, string $body, array $data = []): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            Log::info('PushNotificationService: sendToUserIds sem utilizadores válidos');

            return;
        }

        $messaging = $this->messaging();
        if ($messaging === null) {
            Log::warning('PushNotificationService: FCM indisponível (sendToUserIds)');

            return;
        }

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $ids)
            ->pluck('token')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            Log::info('PushNotificationService: sem tokens FCM para os utilizadores solicitados', ['count_users' => count($ids)]);

            return;
        }

        $payload = $this->normalizeDataForFcm(array_merge([
            'type' => 'admin_broadcast',
        ], $data));

        $sent = 0;
        foreach ($tokens as $token) {
            try {
                // Canal Android alinhado com Flutter (NotificationService: ponto_alerts) + prioridade alta.
                $message = CloudMessage::fromArray([
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $payload,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'ponto_alerts',
                            'sound' => 'default',
                        ],
                    ],
                ]);
                $messaging->send($message);
                $sent++;
            } catch (Throwable $e) {
                Log::warning('FCM sendToUserIds: '.$e->getMessage(), ['token' => substr((string) $token, 0, 20)]);
            }
        }

        Log::info('PushNotificationService: admin broadcast enviado', [
            'tokens_ok' => $sent,
            'tokens_total' => count($tokens),
            'user_ids' => count($ids),
        ]);
    }

    /**
     * O payload `data` da FCM HTTP v1 exige valores em string.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeDataForFcm(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $k = (string) $key;
            if ($value === null) {
                $out[$k] = '';
            } elseif (is_scalar($value)) {
                $out[$k] = (string) $value;
            } else {
                $out[$k] = json_encode($value, JSON_THROW_ON_ERROR);
            }
        }

        return $out;
    }

    /**
     * Notifica admins/gestores da empresa sobre tentativas de fraude.
     *
     * @param FraudAttempt[] $attempts
     */
    public function notifyFraudAttempts(array $attempts, Employee $employee): void
    {
        if (empty($attempts)) {
            return;
        }
        $messaging = $this->messaging();
        if ($messaging === null) {
            Log::warning('PushNotificationService: FCM indisponível (fraude)');

            return;
        }

        $companyId   = $employee->company_id;
        $employeeName = $employee->user?->name ?? 'Colaborador #'.$employee->id;
        $rules        = array_unique(array_map(fn($a) => $a->getRuleLabel(), $attempts));
        $actionTaken  = $attempts[0]->action_taken;

        $title = $actionTaken === 'blocked'
            ? 'Ponto bloqueado por fraude'
            : 'Tentativa de fraude detectada';
        $body = $employeeName . ': ' . implode(', ', $rules);

        $notification = Notification::create($title, $body);

        $adminGestorIds = User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', ['admin', 'gestor'])
            ->pluck('id');

        // Admin global (sem company_id) também recebe
        $globalAdminIds = User::query()
            ->whereNull('company_id')
            ->where('role', 'admin')
            ->pluck('id');

        $allIds = $adminGestorIds->merge($globalAdminIds)->unique();

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $allIds)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            Log::info('PushNotificationService: sem tokens FCM para alerta de fraude');
        } else {
            $fraudData = $this->normalizeDataForFcm([
                'type' => 'fraud_alert',
                'company_id' => (string) $companyId,
            ]);

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withData($fraudData);
                    $messaging->send($message);
                } catch (Throwable $e) {
                    Log::warning('FCM fraud alert: '.$e->getMessage());
                }
            }
        }

        FraudAttempt::query()
            ->whereIn('id', array_map(fn($a) => $a->id, $attempts))
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);
    }

    private function messaging(): ?Messaging
    {
        try {
            return app(Messaging::class);
        } catch (Throwable $e) {
            Log::warning('PushNotificationService: não foi possível inicializar FCM', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
