<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Services\PayPeriodClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayPeriodClosureWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $user = $request->user();
        $companyId = $request->get('company_id');

        $companies = Company::where('active', true)->orderBy('name')->get();

        $query = PayPeriodClosure::query()
            ->with(['company', 'closedByUser'])
            ->withCount([
                'acknowledgements as pending_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE),
                'acknowledgements as approved_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_APROVADO),
                'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO),
            ]);

        if (! $user->isAdmin()) {
            abort_unless($user->company_id, 403, 'Empresa não associada ao utilizador.');
            $query->where('company_id', $user->company_id);
        } elseif ($companyId) {
            $query->where('company_id', $companyId);
        }

        $closures = $query->orderByDesc('period_end')->paginate(20)->withQueryString();

        return view('web.pay-period-closures.index', compact('closures', 'companies', 'companyId'));
    }

    public function store(Request $request, PayPeriodClosureService $service): RedirectResponse
    {
        $this->authorize('manage-employees');

        $user = $request->user();

        $rules = [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:5000',
        ];

        if ($user->isAdmin()) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

        $companyId = $user->isAdmin()
            ? (int) $validated['company_id']
            : (int) $user->company_id;

        if (! $companyId) {
            return back()->with('error', 'Utilizador sem empresa associada.')->withInput();
        }

        $service->closePeriod(
            $user,
            $companyId,
            $validated['period_start'],
            $validated['period_end'],
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('painel.pay-period-closures.index')
            ->with('success', 'Período fechado. Os colaboradores podem consultar e responder ao espelho na app.');
    }
}
