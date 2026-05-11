<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodClosure;
use App\Services\PayPeriodClosureService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
                'acknowledgements as rejected_count' => fn ($q) => $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO)->whereNull('superseded_at'),
                'acknowledgements as people_total',
            ]);

        if (! $user->isAdmin()) {
            abort_unless($user->company_id, 403, 'Empresa não associada ao utilizador.');
            $query->where('company_id', $user->company_id);
        } elseif ($companyId) {
            $query->where('company_id', $companyId);
        }

        $closures = $query->orderByDesc('period_end')->paginate(20)->withQueryString();

        $dataCompanyId = $user->isAdmin()
            ? (
                ($companyId !== null && $companyId !== '')
                    ? (int) $companyId
                    : ($companies->count() === 1 ? (int) $companies->first()->id : null)
            )
            : (int) $user->company_id;

        $departments = $dataCompanyId
            ? Department::query()->where('company_id', $dataCompanyId)->where('active', true)->orderBy('name')->get()
            : collect();

        $employeesForClosure = $dataCompanyId
            ? Employee::query()
                ->where('company_id', $dataCompanyId)
                ->where('active', true)
                ->with('user')
                ->orderBy('id')
                ->get()
            : collect();

        $selectedCompanyForForm = null;
        if ($user->isAdmin()) {
            $selectedCompanyForForm = (($companyId !== null && $companyId !== '')
                ? (int) $companyId
                : $dataCompanyId);
        }

        $correctionSourceClosure = null;
        $correctionRejectedAcks = collect();

        if ($request->filled('correction_from_closure_id')) {
            $cid = (int) $request->query('correction_from_closure_id');
            $cq = PayPeriodClosure::query()->with([
                'company',
                'acknowledgements' => function ($q) {
                    $q->where('status', EmployeePayPeriodAcknowledgement::STATUS_REJEITADO)
                        ->whereNull('superseded_at')
                        ->orderBy('employee_id')
                        ->with('employee.user');
                },
            ])->whereKey($cid);

            if (! $user->isAdmin()) {
                abort_unless($user->company_id, 403, 'Empresa não associada ao utilizador.');
                $cq->where('company_id', $user->company_id);
            }

            $correctionSourceClosure = $cq->first();
            if ($correctionSourceClosure && $correctionSourceClosure->acknowledgements->isNotEmpty()) {
                $correctionRejectedAcks = $correctionSourceClosure->acknowledgements;
            } else {
                $correctionSourceClosure = null;
                $correctionRejectedAcks = collect();
            }
        }

        return view('web.pay-period-closures.index', compact(
            'closures',
            'companies',
            'companyId',
            'departments',
            'employeesForClosure',
            'dataCompanyId',
            'selectedCompanyForForm',
            'correctionSourceClosure',
            'correctionRejectedAcks',
        ));
    }

    public function store(Request $request, PayPeriodClosureService $service): RedirectResponse
    {
        $this->authorize('manage-employees');

        $user = $request->user();

        $tz = config('app.timezone');

        $supersedesInput = collect((array) $request->input('supersedes_acknowledgement_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($supersedesInput !== []) {
            $rules = [
                'period_start' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
                'period_end' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
                'notes' => 'nullable|string|max:5000',
                'supersedes_acknowledgement_ids' => 'required|array|min:1',
                'supersedes_acknowledgement_ids.*' => 'integer',
            ];
            if ($user->isAdmin()) {
                $rules['company_id'] = 'required|exists:companies,id';
            }

            $validated = $request->validate($rules);

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

            try {
                $service->closePeriod(
                    $user,
                    $companyId,
                    $start->toDateString(),
                    $end->toDateString(),
                    $validated['notes'] ?? null,
                    [],
                    array_values(array_unique(array_map('intval', $validated['supersedes_acknowledgement_ids']))),
                );
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            return redirect()
                ->route('painel.pay-period-closures.index')
                ->with('success', 'Espelho de correção gerado. Os colaboradores voltam a ver o período pendente na app.');
        }

        $rules = [
            'period_start' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'period_end' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'notes' => 'nullable|string|max:5000',
            'closure_scope' => 'required|in:company,departments,employees',
            'department_ids' => 'exclude_unless:closure_scope,departments|required|array|min:1',
            'department_ids.*' => 'integer',
            'employee_ids' => 'exclude_unless:closure_scope,employees|required|array|min:1',
            'employee_ids.*' => 'integer',
        ];

        if ($user->isAdmin()) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

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

        $employeeIds = $service->resolveTargetEmployeeIds(
            $companyId,
            $validated['closure_scope'],
            $validated['department_ids'] ?? [],
            $validated['employee_ids'] ?? [],
        );

        try {
            $service->closePeriod(
                $user,
                $companyId,
                $start->toDateString(),
                $end->toDateString(),
                $validated['notes'] ?? null,
                $employeeIds,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('painel.pay-period-closures.index')
            ->with('success', 'Período fechado. Os colaboradores podem consultar e responder ao espelho na app.');
    }

    public function destroy(Request $request, PayPeriodClosure $payPeriodClosure): RedirectResponse
    {
        $this->authorize('manage-employees');

        $user = $request->user();

        if (! $user->isAdmin()) {
            abort_unless(
                $user->company_id && (int) $payPeriodClosure->company_id === (int) $user->company_id,
                403,
                'Sem permissão para este fecho.'
            );
        }

        if (! $payPeriodClosure->canDeleteWhileAllPending()) {
            return back()->with(
                'error',
                'Só é possível excluir enquanto todos os colaboradores estiverem pendentes (ninguém aceitou nem contestou).'
            );
        }

        $payPeriodClosure->delete();

        return redirect()
            ->route('painel.pay-period-closures.index')
            ->with('success', 'Fecho removido. Os colaboradores deixam de ver este espelho na app.');
    }
}
