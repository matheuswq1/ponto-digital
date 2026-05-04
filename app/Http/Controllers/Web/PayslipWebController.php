<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\FirebaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayslipWebController extends Controller
{
    public function __construct(
        private readonly FirebaseStorageService $storage
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $companyId = $request->get('company_id');
        $employeeId = $request->get('employee_id');
        $year = $request->get('year', now()->year);
        $month = $request->get('month');

        $query = Payslip::query()
            ->with(['employee.user', 'company'])
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month')
            ->orderBy('id');

        if (auth()->user()->isGestor()) {
            $query->where('company_id', auth()->user()->company_id);
        } elseif ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($year) {
            $query->where('reference_year', $year);
        }
        if ($month) {
            $query->where('reference_month', $month);
        }

        $payslips = $query->paginate(30)->withQueryString();

        $companies = auth()->user()->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', auth()->user()->company_id)->get();

        $employees = collect();
        if ($companyId || auth()->user()->isGestor()) {
            $cid = auth()->user()->isGestor() ? auth()->user()->company_id : $companyId;
            $employees = Employee::where('company_id', $cid)
                ->where('active', true)
                ->with('user')
                ->orderBy('id')
                ->get();
        }

        return view('web.payslips.index', compact(
            'payslips', 'companies', 'employees',
            'companyId', 'employeeId', 'year', 'month'
        ));
    }

    public function create(): View
    {
        $this->authorize('manage-employees');

        $companies = auth()->user()->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', auth()->user()->company_id)->get();

        return view('web.payslips.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $validated = $request->validate([
            'company_id'      => 'required|exists:companies,id',
            'employee_ids'    => 'required|array|min:1',
            'employee_ids.*'  => 'exists:employees,id',
            'reference_month' => 'required|integer|between:1,12',
            'reference_year'  => 'required|integer|min:2020|max:2099',
            'description'     => 'nullable|string|max:120',
            'files'           => 'required|array|min:1',
            'files.*'         => 'required|file|mimes:pdf|max:10240',
        ]);

        if (auth()->user()->isGestor()) {
            $validated['company_id'] = auth()->user()->company_id;
        }

        $files   = $request->file('files');
        $empIds  = $validated['employee_ids'];
        $created = 0;

        foreach ($empIds as $i => $empId) {
            $file = $files[$i] ?? $files[0];

            $employee = Employee::where('id', $empId)
                ->where('company_id', $validated['company_id'])
                ->firstOrFail();

            // Remove holerite duplicado do mesmo mês/ano se existir
            Payslip::where('employee_id', $empId)
                ->where('reference_month', $validated['reference_month'])
                ->where('reference_year', $validated['reference_year'])
                ->delete();

            $url = $this->storage->uploadPayslip(
                $file,
                (int) $validated['company_id'],
                $employee->id,
                (int) $validated['reference_year'],
                (int) $validated['reference_month']
            );

            Payslip::create([
                'company_id'      => $validated['company_id'],
                'employee_id'     => $employee->id,
                'reference_month' => $validated['reference_month'],
                'reference_year'  => $validated['reference_year'],
                'file_url'        => $url,
                'file_name'       => $file->getClientOriginalName(),
                'file_size'       => $file->getSize(),
                'description'     => $validated['description'] ?? null,
                'notified'        => false,
            ]);

            $created++;
        }

        return redirect()->route('painel.payslips.index')
            ->with('success', "$created holerite(s) enviado(s) com sucesso.");
    }

    public function destroy(Payslip $payslip): RedirectResponse
    {
        $this->authorize('manage-employees');

        if (auth()->user()->isGestor() && $payslip->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $payslip->delete();

        return back()->with('success', 'Holerite removido.');
    }

    /** AJAX: retorna colaboradores de uma empresa para popular o select */
    public function employeesByCompany(int $companyId): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage-employees');

        if (auth()->user()->isGestor() && (int) auth()->user()->company_id !== $companyId) {
            abort(403);
        }

        $employees = Employee::where('company_id', $companyId)
            ->where('active', true)
            ->with('user')
            ->get()
            ->map(fn ($e) => [
                'id'   => $e->id,
                'name' => $e->user?->name ?? "Funcionário #{$e->id}",
            ]);

        return response()->json($employees);
    }
}
