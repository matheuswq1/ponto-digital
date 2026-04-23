<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\FaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaceController extends Controller
{
    public function __construct(private readonly FaceService $faceService) {}

    /**
     * POST /api/v1/face/enroll
     * Cadastra o rosto do funcionário autenticado.
     * Deve ser chamado no 1º login, via app.
     */
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:8192',
        ]);

        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        $path = $request->file('photo')->store('tmp/faces');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $result = $this->faceService->enroll($employee->id, $fullPath);
            $employee->update(['face_enrolled' => true]);

            return response()->json([
                'message' => 'Rosto cadastrado com sucesso.',
                'face_enrolled' => true,
            ]);
        } catch (\RuntimeException $e) {
            $msg = $this->friendlyError($e->getMessage(), $e->getCode());

            return response()->json(['message' => $msg], 422);
        } finally {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * POST /api/v1/face/verify
     * Verifica o rosto contra o embedding cadastrado.
     * Usado durante o bater ponto.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:8192',
        ]);

        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        if (! $employee->face_enrolled) {
            return response()->json([
                'message' => 'Rosto ainda não cadastrado.',
                'face_enrolled' => false,
                'match' => false,
                'score' => 0,
            ], 200);
        }

        $path = $request->file('photo')->store('tmp/faces');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $result = $this->faceService->verify($employee->id, $fullPath);

            return response()->json($result);
        } catch (\RuntimeException $e) {
            $msg = $this->friendlyError($e->getMessage(), $e->getCode());

            return response()->json(['message' => $msg], 422);
        } finally {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * DELETE /api/v1/face/enroll
     * Remove o embedding (admin/gestor pode passar employee_id; funcionário remove o seu próprio).
     */
    public function deleteEnroll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isGestor()) {
            $request->validate(['employee_id' => 'required|integer|exists:employees,id']);
            $employee = Employee::findOrFail($request->integer('employee_id'));
            if ($user->isGestor()) {
                $cid = $user->employee?->company_id ?? $user->company_id;
                if (! $cid || (int) $employee->company_id !== (int) $cid) {
                    return response()->json(['message' => 'Sem permissão.'], 403);
                }
            }
        } else {
            $employee = $user->employee;
        }

        if (! $employee) {
            return response()->json(['message' => 'Funcionário não encontrado.'], 404);
        }

        try {
            $this->faceService->deleteEnrollment($employee->id);
            $employee->update(['face_enrolled' => false]);

            return response()->json(['message' => 'Embedding removido com sucesso.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function friendlyError(string $raw, int $code): string
    {
        if ($code === 404) {
            return 'Nenhum rosto cadastrado para este colaborador.';
        }
        if (str_contains($raw, 'Nenhum rosto')) {
            return 'Nenhum rosto detectado. Posicione o rosto no centro e tente novamente.';
        }
        if (str_contains($raw, 'Mais de um rosto')) {
            return 'Mais de um rosto na imagem. Certifique-se de estar sozinho.';
        }

        return $raw;
    }
}
