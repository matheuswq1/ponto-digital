<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Employee;
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
            return;
        }

        $user = User::query()->find($edit->edited_by);
        if (! $user) {
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
        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->all();

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)->withNotification($notification);
                $messaging->send($message);
            } catch (Throwable $e) {
                Log::warning('FCM: '.$e->getMessage());
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
        $messaging = $this->messaging();
        if ($messaging === null) {
            return;
        }

        $userId = $employee->user_id;
        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            Log::info("PushNotificationService: sem tokens para employee {$employee->id}");
            return;
        }

        $notification = Notification::create($payload['title'], $payload['body']);

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($payload['data'] ?? []);
                $messaging->send($message);
            } catch (Throwable $e) {
                Log::warning('FCM sendToEmployee: ' . $e->getMessage(), ['token' => substr($token, 0, 20)]);
            }
        }
    }

    private function messaging(): ?Messaging
    {
        try {
            return app(Messaging::class);
        } catch (Throwable) {
            return null;
        }
    }
}
