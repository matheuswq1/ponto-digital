<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkDay;
use App\Jobs\ProcessWorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
// FraudCheckService é resolvido via app() para evitar injeção circular com PushNotificationService

class TimeRecordService
{
    public function __construct(
        private readonly GeolocationService $geolocationService,
        private readonly FirebaseStorageService $firebaseStorageService,
    ) {}

    public function register(Employee $employee, array $data): TimeRecord
    {
        $this->validateSequence($employee, $data['type']);

        if (isset($data['latitude'], $data['longitude']) && $employee->company->require_geolocation) {
            $this->geolocationService->validateGeofence(
                $employee->company,
                (float) $data['latitude'],
                (float) $data['longitude']
            );
        }

        // Anti-fraude: registar tentativa e bloquear se configurado
        $fraudResult = app(FraudCheckService::class)->check($employee, array_merge($data, [
            'ip_address' => isset($data['source']) && $data['source'] === 'totem' ? null : request()->ip(),
        ]));

        if (count($fraudResult->attempts) > 0) {
            app(\App\Services\PushNotificationService::class)->notifyFraudAttempts($fraudResult->attempts, $employee);
        }

        if ($fraudResult->blocked) {
            throw ValidationException::withMessages([
                'fraud' => ['Registo bloqueado por política de segurança: ' . implode(', ', $fraudResult->violations)],
            ]);
        }

        return DB::transaction(function () use ($employee, $data) {
            $record = TimeRecord::create([
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'datetime' => Carbon::now('UTC'),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
                'ip_address' => isset($data['source']) && $data['source'] === 'totem' ? null : request()->ip(),
                'device_info' => isset($data['source']) && $data['source'] === 'totem' ? 'totem' : request()->userAgent(),
                'device_id' => $data['device_id'] ?? null,
                'is_mock_location' => $data['is_mock_location'] ?? false,
                'offline' => $data['offline'] ?? false,
                'synced_at' => isset($data['offline']) && $data['offline'] ? now() : null,
                'status' => 'pendente',
            ]);

            // Recalcula o dia de trabalho a cada saída
            if ($data['type'] === 'saida') {
                $tz = config('app.timezone', 'America/Sao_Paulo');
                ProcessWorkDay::dispatch($employee, $record->datetime->copy()->setTimezone($tz)->toDateString());
            }

            return $record;
        });
    }

    public function registerOfflineBatch(Employee $employee, array $records): array
    {
        $registered = [];
        $failed = [];

        foreach ($records as $recordData) {
            try {
                $this->validateSequenceForDatetime($employee, $recordData['type'], $recordData['datetime']);

                // Anti-fraude offline: apenas registar (nunca bloquear ponto offline)
                $fraudResult = app(FraudCheckService::class)->check($employee, array_merge($recordData, [
                    'ip_address' => request()->ip(),
                ]));
                if (count($fraudResult->attempts) > 0) {
                    app(\App\Services\PushNotificationService::class)->notifyFraudAttempts($fraudResult->attempts, $employee);
                }

                $record = TimeRecord::create([
                    'employee_id' => $employee->id,
                    'type' => $recordData['type'],
                    'datetime' => Carbon::parse($recordData['datetime'])->utc(),
                    'latitude' => $recordData['latitude'] ?? null,
                    'longitude' => $recordData['longitude'] ?? null,
                    'accuracy' => $recordData['accuracy'] ?? null,
                    'photo_url' => $recordData['photo_url'] ?? null,
                    'ip_address' => request()->ip(),
                    'device_info' => request()->userAgent(),
                    'device_id' => $recordData['device_id'] ?? null,
                    'is_mock_location' => $recordData['is_mock_location'] ?? false,
                    'offline' => true,
                    'synced_at' => now(),
                    'status' => 'pendente',
                ]);

                $registered[] = $record;
            } catch (\Exception $e) {
                $failed[] = [
                    'data' => $recordData,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['registered' => $registered, 'failed' => $failed];
    }

    public function validateSequence(Employee $employee, string $type): void
    {
        $employee->loadMissing('company');
        $maxRecords = $employee->company?->max_daily_records ?? 10;

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $startOfDay = Carbon::now($tz)->startOfDay()->utc();
        $endOfDay   = Carbon::now($tz)->endOfDay()->utc();

        $todayRecords = $employee->timeRecords()
            ->whereBetween('datetime', [$startOfDay, $endOfDay])
            ->orderBy('datetime')
            ->get();

        $count = $todayRecords->count();
        $lastType = $todayRecords->last()?->type;

        $validNext = $this->getNextValidTypes($lastType, $count, $maxRecords);

        if (!in_array($type, $validNext)) {
            if (empty($validNext)) {
                throw ValidationException::withMessages([
                    'type' => ["Limite diário de batidas atingido ({$maxRecords})."]
                ]);
            }
            throw ValidationException::withMessages([
                'type' => ["Tipo de ponto inválido. Próximo esperado: " . implode(' ou ', $validNext)]
            ]);
        }
    }

    private function validateSequenceForDatetime(Employee $employee, string $type, string $datetime): void
    {
        $date = Carbon::parse($datetime)->toDateString();

        $records = $employee->timeRecords()
            ->whereDate('datetime', $date)
            ->where('datetime', '<', $datetime)
            ->orderBy('datetime')
            ->get();

        $count = $records->count();
        $lastType = $records->last()?->type;
        $maxRecords = $employee->company?->max_daily_records ?? 10;

        $validNext = $this->getNextValidTypes($lastType, $count, $maxRecords);

        if (!in_array($type, $validNext)) {
            throw new \InvalidArgumentException(
                "Tipo de ponto inválido para data {$date}. Esperado: " . implode(' ou ', $validNext)
            );
        }
    }

    public function getNextValidTypes(?string $lastType, int $currentCount = 0, int $maxRecords = 10): array
    {
        // Limite atingido
        if ($currentCount >= $maxRecords) {
            return [];
        }

        return match ($lastType) {
            null, 'saida' => ['entrada'],
            'entrada'     => ['saida'],
            default       => ['entrada'],
        };
    }

    public function getEmployeeRecords(Employee $employee, ?string $startDate = null, ?string $endDate = null)
    {
        $query = $employee->timeRecords()
            ->with('edits.editor')
            ->orderByDesc('datetime');

        if ($startDate) {
            $query->where('datetime', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('datetime', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query->paginate(30);
    }

    public function requestEdit(TimeRecord $record, array $data, int $editedByUserId): \App\Models\TimeRecordEdit
    {
        if ($record->is_edited) {
            throw ValidationException::withMessages([
                'record' => ['Este registro já foi editado anteriormente.']
            ]);
        }

        // Bloquear pedido duplicado enquanto o anterior estiver pendente
        $hasPending = $record->edits()->where('status', 'pendente')->exists();
        if ($hasPending) {
            throw ValidationException::withMessages([
                'record' => ['Já existe uma solicitação de correção pendente para este registro. Aguarde a resposta do gestor.']
            ]);
        }

        return $record->edits()->create([
            'edited_by' => $editedByUserId,
            'original_datetime' => $record->datetime,
            'new_datetime' => Carbon::parse($data['new_datetime'])->utc(),
            'original_type' => $record->type,
            'new_type' => $data['new_type'] ?? $record->type,
            'justification' => $data['justification'],
            'status' => 'pendente',
        ]);
    }
}
