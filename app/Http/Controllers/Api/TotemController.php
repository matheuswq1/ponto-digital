<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\FaceService;
use App\Services\FirebaseStorageService;
use App\Services\TimeRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TotemController extends Controller
{
    public function __construct(
        private readonly FaceService $faceService,
        private readonly TimeRecordService $timeRecordService,
        private readonly FirebaseStorageService $firebaseStorageService,
    ) {}

    /**
     * POST /api/v1/totem/identify
     *
     * Recebe uma foto e identifica o funcionário dentro da empresa do totem.
     * Retorna os dados do funcionário e o próximo tipo de ponto disponível.
     */
    public function identify(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:8192',
        ]);

        $totemUser = $request->user();
        $companyId = $totemUser->company_id;

        if (! $companyId) {
            return response()->json(['message' => 'Totem não vinculado a nenhuma empresa.'], 422);
        }

        // Busca IDs dos funcionários ativos com rosto cadastrado nesta empresa
        $employeeIds = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->where('face_enrolled', true)
            ->pluck('id')
            ->all();

        if (empty($employeeIds)) {
            return response()->json([
                'match' => false,
                'message' => 'Nenhum funcionário com rosto cadastrado nesta empresa.',
            ]);
        }

        $path = $request->file('photo')->store('tmp/faces');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $result = $this->faceService->identify($employeeIds, $fullPath);

            // Serviço de IA não encontrou ninguém
            if (empty($result['match']) || empty($result['employee_id'])) {
                return response()->json([
                    'match' => false,
                    'message' => $result['message'] ?? 'Rosto não reconhecido.',
                    'score' => $result['score'] ?? 0,
                    'distance' => $result['distance'] ?? 1,
                    'threshold' => $result['threshold'] ?? 0.55,
                ]);
            }

            $employee = Employee::with('user')->find($result['employee_id']);

            if (! $employee || (int) $employee->company_id !== (int) $companyId) {
                return response()->json([
                    'match' => false,
                    'message' => 'Funcionário não pertence a esta empresa.',
                ]);
            }

            // Calcula próximo tipo de ponto
            $employee->loadMissing('company');
            $maxRecords = $employee->company?->max_daily_records ?? 10;

            $todayRecords = $employee->timeRecords()
                ->whereDate('datetime', today())
                ->orderBy('datetime')
                ->get();

            $count = $todayRecords->count();
            $lastType = $todayRecords->last()?->type;
            $nextTypes = $this->timeRecordService->getNextValidTypes($lastType, $count, $maxRecords);

            return response()->json([
                'match' => true,
                'score' => $result['score'] ?? 1,
                'distance' => $result['distance'] ?? 0,
                'threshold' => $result['threshold'] ?? 0.55,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->user?->name ?? 'Funcionário',
                    'cargo' => $employee->cargo,
                    'face_enrolled' => $employee->face_enrolled,
                ],
                'next_type' => $nextTypes[0] ?? null,
                'next_types' => $nextTypes,
                'is_complete' => empty($nextTypes),
                'max_daily_records' => $maxRecords,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            if (isset($fullPath) && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * POST /api/v1/totem/register-point
     *
     * Registra o ponto de um funcionário identificado.
     * Opcionalmente faz upload da foto para evidência.
     */
    public function registerPoint(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'type'        => 'required|in:entrada,saida',
            'photo'       => 'nullable|image|max:8192',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        $totemUser = $request->user();
        $companyId = $totemUser->company_id;

        $employee = Employee::with('user')->findOrFail($request->integer('employee_id'));

        // Garante que o funcionário pertence à empresa do totem
        if ((int) $employee->company_id !== (int) $companyId) {
            return response()->json(['message' => 'Sem permissão para registrar ponto deste funcionário.'], 403);
        }

        $data = [
            'type'      => $request->type,
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
            'source'    => 'totem',
        ];

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $this->firebaseStorageService->uploadTimeRecordPhoto(
                $request->file('photo'),
                $employee->id,
                $data['type']
            );
        }

        $record = $this->timeRecordService->register($employee, $data);

        $tz = config('app.timezone', 'America/Sao_Paulo');

        return response()->json([
            'message'        => 'Ponto registrado com sucesso.',
            'employee_name'  => $employee->user?->name ?? 'Funcionário',
            'type'           => $record->type,
            'datetime'       => $record->datetime?->setTimezone($tz)->format('Y-m-d\TH:i:s'),
        ], 201);
    }

}
