<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $communications = Communication::where('company_id', $employee->company_id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->paginate(30);

        return response()->json([
            'data' => $communications->map(fn ($c) => [
                'id'            => $c->id,
                'title'         => $c->title,
                'body'          => $c->body,
                'type'          => $c->type,
                'type_label'    => $c->getTypeLabel(),
                'pinned'        => $c->pinned,
                'published_at'  => $c->published_at?->toISOString(),
                'expires_at'    => $c->expires_at?->toISOString(),
            ]),
            'meta' => [
                'total'        => $communications->total(),
                'current_page' => $communications->currentPage(),
                'last_page'    => $communications->lastPage(),
            ],
        ]);
    }
}
