<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'nullable|string|max:32|in:android,ios,web',
        ]);

        $user = $request->user();
        DeviceToken::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $data['token'],
            ],
            [
                'platform' => $data['platform'] ?? 'android',
            ]
        );

        return response()->json(['message' => 'Token registrado.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
        ]);

        $request->user()->deviceTokens()
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Token removido.']);
    }
}
