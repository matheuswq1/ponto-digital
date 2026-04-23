<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $user = Auth::user();

        if (! $user->active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Sua conta está desativada.'],
            ]);
        }

        $token = $user->createToken(
            $request->device_name ?? 'api-token',
            ['*'],
            now()->addDays(30)
        );

        $user->load(['employee.company', 'company']);

        $faceEnrolled = $user->employee
            ? (bool) $user->employee->face_enrolled
            : true;

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'user' => new UserResource($user),
            // Colaboradores sem rosto vão para o enrolamento; gestores/admin sem vínculo de colaborador não.
            'face_enrolled' => $faceEnrolled,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['employee.company', 'company'])),
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            'api-token',
            ['*'],
            now()->addDays(30)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }
}
