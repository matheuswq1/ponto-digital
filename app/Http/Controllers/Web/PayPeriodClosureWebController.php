<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Services\PayPeriodClosureService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

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
            'period_start' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'period_end' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'notes' => 'nullable|string|max:5000',
        ];

        if ($user->isAdmin()) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

        $tz = config('app.timezone');
        try {
            $start = Carbon::createFromFormat('d/m/Y', $validated['period_start'], $tz)->startOfDay();
            $end = Carbon::createFromFormat('d/m/Y', $validated['period_end'], $tz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'period_start' => ['Use datas válidas no formato dd/mm/aaaa.'],
            ]);
        }

        if ($start->format('d/m/Y') !== $validated['period_start'] || $end->format('d/m/Y') !== $validated['period_end']) {
            throw ValidationException::withMessages([
                'period_start' => ['Data inválida (ex.: dia ou mês inexistente).'],
            ]);
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'period_end' => ['A data final deve ser igual ou posterior à inicial.'],
            ]);
        }

        $companyId = $user->isAdmin()
            ? (int) $validated['company_id']
            : (int) $user->company_id;

        if (! $companyId) {
            return back()->with('error', 'Utilizador sem empresa associada.')->withInput();
        }

        $service->closePeriod(
            $user,
            $companyId,
            $start->toDateString(),
            $end->toDateString(),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('painel.pay-period-closures.index')
            ->with('success', 'Período fechado. Os colaboradores podem consultar e responder ao espelho na app.');
    }
}
