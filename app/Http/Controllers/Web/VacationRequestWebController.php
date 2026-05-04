<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VacationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacationRequestWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $query = VacationRequest::query()
            ->with(['employee.user', 'reviewer'])
            ->orderByRaw("FIELD(status,'pendente','aprovado','rejeitado')")
            ->orderByDesc('created_at');

        if (auth()->user()->isGestor()) {
            $query->where('company_id', auth()->user()->company_id);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('web.vacation_requests.index', compact('requests'));
    }

    public function approve(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $this->authorize('manage-employees');
        $this->checkAccess($vacationRequest);

        $request->validate(['notes' => 'nullable|string|max:500']);

        $vacationRequest->update([
            'status'       => 'aprovado',
            'reviewed_by'  => auth()->id(),
            'review_notes' => $request->notes,
            'reviewed_at'  => now(),
        ]);

        return back()->with('success', 'Solicitação de férias aprovada.');
    }

    public function reject(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $this->authorize('manage-employees');
        $this->checkAccess($vacationRequest);

        $request->validate(['notes' => 'nullable|string|max:500']);

        $vacationRequest->update([
            'status'       => 'rejeitado',
            'reviewed_by'  => auth()->id(),
            'review_notes' => $request->notes,
            'reviewed_at'  => now(),
        ]);

        return back()->with('success', 'Solicitação de férias rejeitada.');
    }

    private function checkAccess(VacationRequest $req): void
    {
        if (auth()->user()->isGestor() && $req->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
