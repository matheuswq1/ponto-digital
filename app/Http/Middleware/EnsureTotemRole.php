<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTotemRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'totem') {
            return response()->json(['message' => 'Acesso restrito a dispositivos totem.'], 403);
        }

        if (! $user->active) {
            return response()->json(['message' => 'Dispositivo totem desativado.'], 403);
        }

        if (! $user->company_id) {
            return response()->json(['message' => 'Totem não vinculado a nenhuma empresa.'], 422);
        }

        return $next($request);
    }
}
