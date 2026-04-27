<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TimeRecordAddition;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdditionRequestWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('approve-edit-requests');

        $additions = TimeRecordAddition::query()
            ->with(['employee.user', 'requester'])
            ->where('status', 'pendente')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('web.addition-requests.index', compact('additions'));
    }

    public function approve(Request $request, TimeRecordAddition $addition): RedirectResponse
    {
        $this->authorize('approve-edit-requests');

        $data = $request->validate(['notes' => 'nullable|string|max:500']);

        if (! $addition->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $addition->approve($request->user(), $data['notes'] ?? null);

        AuditService::log(
            $request->user(),
            'time_record_addition.approve',
            'Adição de ponto aprovada',
            $addition->fresh(),
            ['notes' => $data['notes'] ?? null],
            $addition->employee?->company_id,
            $request
        );

        // Notificar colaborador
        if ($addition->employee) {
            app(\App\Services\PushNotificationService::class)->sendToEmployee(
                $addition->employee,
                [
                    'title' => 'Adição de ponto aprovada',
                    'body'  => 'Seu ponto de ' . ucfirst($addition->type) . ' em ' . $addition->datetime_local?->format('d/m/Y H:i') . ' foi adicionado.',
                    'data'  => ['type' => 'point_addition_approved'],
                ]
            );
        }

        return back()->with('success', 'Ponto adicionado e aprovado.');
    }

    public function reject(Request $request, TimeRecordAddition $addition): RedirectResponse
    {
        $this->authorize('approve-edit-requests');

        $data = $request->validate(['notes' => 'required|string|min:10|max:500']);

        if (! $addition->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $addition->reject($request->user(), $data['notes']);

        AuditService::log(
            $request->user(),
            'time_record_addition.reject',
            'Adição de ponto rejeitada: ' . $data['notes'],
            $addition->fresh(),
            null,
            $addition->employee?->company_id,
            $request
        );

        if ($addition->employee) {
            app(\App\Services\PushNotificationService::class)->sendToEmployee(
                $addition->employee,
                [
                    'title' => 'Adição de ponto rejeitada',
                    'body'  => 'Sua solicitação de ponto foi rejeitada. Motivo: ' . mb_substr($data['notes'], 0, 100),
                    'data'  => ['type' => 'point_addition_rejected'],
                ]
            );
        }

        return back()->with('success', 'Solicitação rejeitada.');
    }
}
