<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentWebController extends Controller
{
    private function companyScopeQuery()
    {
        $q = Department::query()->with('company')->withCount('employees');
        if (auth()->user()->isGestor() && auth()->user()->company_id) {
            $q->where('company_id', auth()->user()->company_id);
        }

        return $q;
    }

    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $search    = $request->get('q');
        $companyId = $request->get('company_id');
        $status    = $request->get('status', 'active');

        $departments = $this->companyScopeQuery()
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($status === 'all', fn ($q) => $q)
            ->when($status === 'active' || ! $status, fn ($q) => $q->where('active', true))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(
                $companyId && (auth()->user()->isAdmin() || (int) $companyId === (int) auth()->user()->company_id),
                fn ($q) => $q->where('company_id', $companyId)
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $companies = auth()->user()->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', auth()->user()->company_id)->get();

        return view('web.departments.index', compact('departments', 'search', 'status', 'companyId', 'companies'));
    }

    public function create(): View
    {
        $this->authorize('manage-employees');

        $companies = auth()->user()->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', auth()->user()->company_id)->get();

        return view('web.departments.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $rules = [
            'name'               => 'required|string|max:120',
            'entry_time'         => 'nullable|date_format:H:i',
            'exit_time'          => 'nullable|date_format:H:i',
            'lunch_minutes'      => 'nullable|integer|min:0|max:300',
            'lunch_by_day'       => 'nullable|array',
            'lunch_by_day.*'     => 'nullable|integer|min:0|max:300',
            'tolerance_minutes'  => 'nullable|integer|min:0|max:120',
            'work_days'          => 'nullable|array',
            'work_days.*'        => 'integer|between:0,6',
            'active'             => 'nullable|boolean',
        ];
        if (auth()->user()->isAdmin()) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

        $companyId = auth()->user()->isAdmin()
            ? (int) $validated['company_id']
            : (int) auth()->user()->company_id;

        $workDays = $request->input('work_days');
        if (! is_array($workDays) || $workDays === []) {
            $workDays = [1, 2, 3, 4, 5];
        }
        $workDays = array_values(array_unique(array_map('intval', $workDays)));
        sort($workDays);

        $lunchResolved = $this->resolveLunchFromRequest($request, (int) ($validated['lunch_minutes'] ?? 60));

        Department::create([
            'company_id'             => $companyId,
            'name'                   => $validated['name'],
            'entry_time'             => $validated['entry_time'] ?? null,
            'exit_time'              => $validated['exit_time'] ?? null,
            'lunch_minutes'          => $lunchResolved['lunch_minutes'],
            'lunch_minutes_by_day'   => $lunchResolved['lunch_minutes_by_day'],
            'tolerance_minutes'      => $validated['tolerance_minutes'] ?? 10,
            'work_days'              => $workDays,
            'active'                 => $request->boolean('active', true),
        ]);

        return redirect()->route('painel.departments.index')
            ->with('success', 'Departamento criado com sucesso.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('manage-employees');
        $this->ensureCanAccessDepartment($department);

        $companies = auth()->user()->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', auth()->user()->company_id)->get();

        return view('web.departments.edit', compact('department', 'companies'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('manage-employees');
        $this->ensureCanAccessDepartment($department);

        $rules = [
            'name'               => 'required|string|max:120',
            'entry_time'         => 'nullable|date_format:H:i',
            'exit_time'          => 'nullable|date_format:H:i',
            'lunch_minutes'      => 'nullable|integer|min:0|max:300',
            'lunch_by_day'       => 'nullable|array',
            'lunch_by_day.*'     => 'nullable|integer|min:0|max:300',
            'tolerance_minutes'  => 'nullable|integer|min:0|max:120',
            'work_days'          => 'nullable|array',
            'work_days.*'        => 'integer|between:0,6',
            'active'             => 'nullable|boolean',
        ];
        if (auth()->user()->isAdmin()) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

        $companyId = auth()->user()->isAdmin()
            ? (int) $validated['company_id']
            : (int) $department->company_id;

        $workDays = $request->input('work_days');
        if (! is_array($workDays) || $workDays === []) {
            $workDays = [1, 2, 3, 4, 5];
        }
        $workDays = array_values(array_unique(array_map('intval', $workDays)));
        sort($workDays);

        $lunchResolved = $this->resolveLunchFromRequest($request, (int) ($validated['lunch_minutes'] ?? 60));

        $department->update([
            'company_id'             => $companyId,
            'name'                   => $validated['name'],
            'entry_time'             => $validated['entry_time'] ?? null,
            'exit_time'              => $validated['exit_time'] ?? null,
            'lunch_minutes'          => $lunchResolved['lunch_minutes'],
            'lunch_minutes_by_day'   => $lunchResolved['lunch_minutes_by_day'],
            'tolerance_minutes'      => $validated['tolerance_minutes'] ?? 10,
            'work_days'              => $workDays,
            'active'                 => $request->boolean('active', true),
        ]);

        return redirect()->route('painel.departments.index')
            ->with('success', 'Departamento atualizado com sucesso.');
    }

    public function dadosEscala(Department $department): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage-employees');
        $this->ensureCanAccessDepartment($department);

        $workDays = is_array($department->work_days) ? $department->work_days : [1,2,3,4,5];

        return response()->json([
            'entry_time'     => $department->entry_time
                ? \Carbon\Carbon::parse($department->entry_time)->format('H:i')
                : null,
            'exit_time'      => $department->exit_time
                ? \Carbon\Carbon::parse($department->exit_time)->format('H:i')
                : null,
            'lunch_minutes'  => $department->lunch_minutes,
            'tolerance'      => $department->tolerance_minutes ?? 5,
            'work_days'      => array_values(array_map('intval', $workDays)),
        ]);
    }

    private function ensureCanAccessDepartment(Department $department): void
    {
        if (auth()->user()->isGestor() && (int) $department->company_id !== (int) auth()->user()->company_id) {
            abort(403);
        }
    }

    /**
     * @return array{lunch_minutes: int, lunch_minutes_by_day: ?array<int, int>}
     */
    private function resolveLunchFromRequest(Request $request, int $defaultLunch): array
    {
        $defaultLunch = max(0, min(300, $defaultLunch));
        $raw          = $request->input('lunch_by_day', []);
        if (! is_array($raw) || $raw === []) {
            return [
                'lunch_minutes'        => $defaultLunch,
                'lunch_minutes_by_day' => null,
            ];
        }
        $vals = [];
        foreach (range(0, 6) as $d) {
            $v = $raw[$d] ?? $raw[(string) $d] ?? null;
            if ($v === null || $v === '') {
                $vals[$d] = $defaultLunch;
            } else {
                $vals[$d] = max(0, min(300, (int) $v));
            }
        }
        $unique = array_unique(array_values($vals));
        if (count($unique) === 1) {
            // Todos os dias iguais → usa o campo "Intervalo padrão" como fonte da verdade
            return [
                'lunch_minutes'        => $defaultLunch,
                'lunch_minutes_by_day' => null,
            ];
        }

        return [
            'lunch_minutes'        => $defaultLunch,
            'lunch_minutes_by_day' => $vals,
        ];
    }
}
