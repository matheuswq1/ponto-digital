<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPushWebController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $push
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('manage-employees');

        $user = $request->user();
        $companies = $user->isAdmin()
            ? Company::where('active', true)->orderBy('name')->get()
            : Company::where('id', $user->company_id)->get();

        $companyId = $user->isGestor()
            ? (int) $user->company_id
            : ($request->integer('company_id') ?: $companies->first()?->id);

        $departments = collect();
        $employees = collect();
        if ($companyId) {
            $departments = Department::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

            $employees = Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereNotNull('user_id')
                ->with('user')
                ->orderBy('id')
                ->get();
        }

        return view('web.admin-push.create', compact(
            'companies',
            'companyId',
            'departments',
            'employees'
        ));
    }

    /**
     * Lista departamentos e colaboradores com app (user_id) para selects dinâmicos.
     */
    public function meta(Request $request): JsonResponse
    {
        $this->authorize('manage-employees');

        $user = $request->user();
        $companyId = (int) $request->query('company_id', 0);

        if ($user->isGestor()) {
            $companyId = (int) $user->company_id;
        }

        if ($companyId < 1 || ! Company::query()->whereKey($companyId)->where('active', true)->exists()) {
            return response()->json(['departments' => [], 'employees' => []], 422);
        }

        if ($user->isGestor() && $companyId !== (int) $user->company_id) {
            abort(403);
        }

        $departments = Department::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name]);

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('user_id')
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => ($e->user?->name ?? 'Colaborador #'.$e->id),
            ]);

        return response()->json([
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $authUser = $request->user();

        $companyRule = $authUser->isAdmin()
            ? ['required', 'integer', Rule::exists('companies', 'id')->where('active', true)]
            : ['nullable'];

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'target' => 'required|in:all,department,user',
            'company_id' => $companyRule,
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ]);

        $companyId = $authUser->isGestor()
            ? (int) $authUser->company_id
            : (int) $validated['company_id'];

        $afterValidator = Validator::make($validated, []);
        if ($validated['target'] === 'department') {
            if (empty($validated['department_id'])) {
                $afterValidator->errors()->add('department_id', 'Selecione um departamento.');
            } else {
                $ok = Department::query()
                    ->whereKey($validated['department_id'])
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->exists();
                if (! $ok) {
                    $afterValidator->errors()->add('department_id', 'Departamento inválido para esta empresa.');
                }
            }
        }
        if ($validated['target'] === 'user') {
            if (empty($validated['employee_id'])) {
                $afterValidator->errors()->add('employee_id', 'Selecione um colaborador.');
            } else {
                $emp = Employee::query()->find($validated['employee_id']);
                if (! $emp || (int) $emp->company_id !== $companyId || ! $emp->active || $emp->user_id === null) {
                    $afterValidator->errors()->add('employee_id', 'Colaborador inválido ou sem conta na app.');
                }
            }
        }
        if ($afterValidator->errors()->isNotEmpty()) {
            return redirect()->back()->withErrors($afterValidator)->withInput();
        }

        $userIds = match ($validated['target']) {
            'all' => Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all(),
            'department' => Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereNotNull('user_id')
                ->where('department_id', $validated['department_id'])
                ->pluck('user_id')
                ->all(),
            'user' => [(int) Employee::query()->findOrFail($validated['employee_id'])->user_id],
        };

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return redirect()->back()
                ->with('error', 'Não há colaboradores elegíveis (activos com conta na app) para este destino.')
                ->withInput();
        }

        $this->push->sendToUserIds(
            $userIds,
            $validated['title'],
            $validated['body'],
            [
                'company_id' => (string) $companyId,
                'target' => $validated['target'],
            ]
        );

        $countRecipients = count(array_unique(array_map('intval', $userIds)));

        return redirect()
            ->route('painel.admin-push.create', $authUser->isAdmin() ? ['company_id' => $companyId] : [])
            ->with('success', 'Notificação enviada para '.$countRecipients.' utilizador(es) com dispositivo registado.');
    }
}
