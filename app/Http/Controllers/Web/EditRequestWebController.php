<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TimeRecordEdit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditRequestWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('approve-edit-requests');

        $edits = TimeRecordEdit::query()
            ->with(['timeRecord.employee.user', 'editor'])
            ->where('status', 'pendente')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('web.edit-requests.index', compact('edits'));
    }

    public function approve(Request $request, TimeRecordEdit $edit): RedirectResponse
    {
        $this->authorize('approve-edit-requests');

        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        if (! $edit->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $edit->approve($request->user(), $data['notes'] ?? null);
        app(\App\Services\PushNotificationService::class)->notifyEditRequestResolved($edit->fresh(), 'aprovado', $data['notes'] ?? null);

        return back()->with('success', 'Correção aprovada.');
    }

    public function reject(Request $request, TimeRecordEdit $edit): RedirectResponse
    {
        $this->authorize('approve-edit-requests');

        $data = $request->validate([
            'notes' => 'required|string|min:10|max:500',
        ]);

        if (! $edit->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $edit->reject($request->user(), $data['notes']);
        app(\App\Services\PushNotificationService::class)->notifyEditRequestResolved($edit->fresh(), 'rejeitado', $data['notes']);

        return back()->with('success', 'Correção rejeitada.');
    }
}
