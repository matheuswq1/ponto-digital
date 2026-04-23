<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeRecordEditResource;
use App\Models\TimeRecord;
use App\Models\TimeRecordEdit;
use App\Services\PushNotificationService;
use App\Services\TimeRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeRecordEditController extends Controller
{
    public function __construct(
        private readonly TimeRecordService $timeRecordService,
        private readonly PushNotificationService $pushNotification,
    ) {}

    public function store(Request $request, TimeRecord $timeRecord): JsonResponse
    {
        $request->validate([
            'new_datetime' => 'required|date',
            'new_type' => 'nullable|in:entrada,saida',
            'justification' => 'required|string|min:20|max:500',
        ]);

        $edit = $this->timeRecordService->requestEdit(
            $timeRecord,
            $request->all(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Solicitação de correção enviada. Aguardando aprovação.',
            'data' => new TimeRecordEditResource($edit),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TimeRecordEdit::with(['timeRecord.employee.user', 'editor', 'approver'])
            ->when(
                !$request->user()->isAdmin() && !$request->user()->isGestor(),
                fn($q) => $q->where('edited_by', $request->user()->id)
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $edits = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => TimeRecordEditResource::collection($edits),
            'meta' => [
                'current_page' => $edits->currentPage(),
                'last_page' => $edits->lastPage(),
                'total' => $edits->total(),
            ],
        ]);
    }

    public function approve(Request $request, TimeRecordEdit $edit): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        if (!$edit->isPending()) {
            return response()->json(['message' => 'Esta solicitação já foi processada.'], 422);
        }

        $edit->approve($request->user(), $request->notes);
        $this->pushNotification->notifyEditRequestResolved($edit->fresh(), 'aprovado', $request->notes);

        return response()->json([
            'message' => 'Correção aprovada com sucesso.',
            'data' => new TimeRecordEditResource($edit->fresh()),
        ]);
    }

    public function reject(Request $request, TimeRecordEdit $edit): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|min:10|max:500',
        ]);

        if (!$edit->isPending()) {
            return response()->json(['message' => 'Esta solicitação já foi processada.'], 422);
        }

        $edit->reject($request->user(), $request->notes);
        $this->pushNotification->notifyEditRequestResolved($edit->fresh(), 'rejeitado', $request->notes);

        return response()->json([
            'message' => 'Correção rejeitada.',
            'data' => new TimeRecordEditResource($edit->fresh()),
        ]);
    }
}
