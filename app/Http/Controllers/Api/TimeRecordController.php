<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTimeRecordRequest;
use App\Http\Requests\Api\SyncOfflineRecordsRequest;
use App\Http\Resources\TimeRecordResource;
use App\Services\FirebaseStorageService;
use App\Services\TimeRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeRecordController extends Controller
{
    public function __construct(
        private readonly TimeRecordService $timeRecordService,
        private readonly FirebaseStorageService $firebaseStorageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $records = $this->timeRecordService->getEmployeeRecords(
            $employee,
            $request->query('start_date'),
            $request->query('end_date')
        );

        return response()->json([
            'data' => TimeRecordResource::collection($records),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function store(StoreTimeRecordRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $this->firebaseStorageService->uploadTimeRecordPhoto(
                $request->file('photo'),
                $employee->id,
                $data['type']
            );
        }

        $record = $this->timeRecordService->register($employee, $data);

        return response()->json([
            'message' => 'Ponto registrado com sucesso.',
            'data' => new TimeRecordResource($record),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $employee = $request->user()->employee;
        $record = $employee->timeRecords()->with('edits.editor')->findOrFail($id);

        return response()->json(['data' => new TimeRecordResource($record)]);
    }

    public function syncOffline(SyncOfflineRecordsRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $result = $this->timeRecordService->registerOfflineBatch(
            $employee,
            $request->validated('records')
        );

        return response()->json([
            'message' => 'Sincronização concluída.',
            'registered' => count($result['registered']),
            'failed' => count($result['failed']),
            'errors' => $result['failed'],
            'data' => TimeRecordResource::collection($result['registered']),
        ]);
    }

    public function getSignedUploadUrl(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:entrada,saida_almoco,volta_almoco,saida',
            'extension' => 'required|in:jpg,jpeg,png,webp',
        ]);

        $employee = $request->user()->employee;

        $result = $this->firebaseStorageService->generateSignedUploadUrl(
            $employee->id,
            $request->type,
            $request->extension
        );

        return response()->json($result);
    }

    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'date' => today()->toDateString(),
                'records' => [],
                'next_type' => null,
                'next_types' => [],
                'is_complete' => false,
            ]);
        }

        $records = $employee->timeRecords()
            ->whereDate('datetime', today())
            ->orderBy('datetime')
            ->get();

        $lastRecord = $records->last();
        $nextTypes = $this->getNextValidTypes($lastRecord?->type);

        return response()->json([
            'date' => today()->toDateString(),
            'records' => TimeRecordResource::collection($records),
            'next_type' => $nextTypes[0] ?? null,
            'next_types' => $nextTypes,
            'is_complete' => $lastRecord?->type === 'saida',
        ]);
    }

    private function getNextValidTypes(?string $lastType): array
    {
        return match ($lastType) {
            null => ['entrada'],
            'entrada' => ['saida_almoco', 'saida'],
            'saida_almoco' => ['volta_almoco'],
            'volta_almoco' => ['saida'],
            'saida' => [],
            default => ['entrada'],
        };
    }
}
