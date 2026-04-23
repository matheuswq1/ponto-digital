<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkDay;
use App\Jobs\ProcessWorkDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        return DB::transaction(function () use ($employee, $data) {
            $record = TimeRecord::create([
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'datetime' => Carbon::now('UTC'),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
                'ip_address' => request()->ip(),
                'device_info' => request()->userAgent(),
                'device_id' => $data['device_id'] ?? null,
                'is_mock_location' => $data['is_mock_location'] ?? false,
                'offline' => $data['offline'] ?? false,
                'synced_at' => isset($data['offline']) && $data['offline'] ? now() : null,
                'status' => 'pendente',
            ]);

            if ($data['type'] === 'saida') {
                ProcessWorkDay::dispatch($employee, $record->datetime->toDateString());
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
        $lastRecord = $employee->timeRecords()
            ->whereDate('datetime', today())
            ->latest('datetime')
            ->first();

        $validNext = $this->getNextValidTypes($lastRecord?->type);

        if (!in_array($type, $validNext)) {
            throw ValidationException::withMessages([
                'type' => [
                    "Tipo de ponto inválido. Próximo esperado: " . implode(' ou ', $validNext)
                ]
            ]);
        }
    }

    private function validateSequenceForDatetime(Employee $employee, string $type, string $datetime): void
    {
        $date = Carbon::parse($datetime)->toDateString();

        $lastRecord = $employee->timeRecords()
            ->whereDate('datetime', $date)
            ->where('datetime', '<', $datetime)
            ->latest('datetime')
            ->first();

        $validNext = $this->getNextValidTypes($lastRecord?->type);

        if (!in_array($type, $validNext)) {
            throw new \InvalidArgumentException(
                "Tipo de ponto inválido para data {$date}. Esperado: " . implode(' ou ', $validNext)
            );
        }
    }

    private function getNextValidTypes(?string $lastType): array
    {
        return match($lastType) {
            null => ['entrada'],
            'entrada' => ['saida_almoco', 'saida'],
            'saida_almoco' => ['volta_almoco'],
            'volta_almoco' => ['saida'],
            'saida' => [],
            default => ['entrada'],
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
