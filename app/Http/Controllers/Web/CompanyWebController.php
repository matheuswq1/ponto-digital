<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AuditService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-companies');

        $search = $request->get('q');
        $status = $request->get('status', 'active');

        $companies = Company::query()
            ->withCount([
                'activeEmployees',
                'users as gestores_count' => fn ($q) => $q->where('role', 'gestor'),
            ])
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($status === 'all', fn ($q) => $q)
            ->when($status === 'active' || ! $status, fn ($q) => $q->where('active', true))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('cnpj', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('web.companies.index', compact('companies', 'search', 'status'));
    }

    public function create(): View
    {
        $this->authorize('manage-companies');

        return view('web.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-companies');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18|unique:companies,cnpj',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zipcode' => 'nullable|string|max:10',
            'gestor_name' => 'required|string|max:255',
            'gestor_email' => 'required|email|unique:users,email',
            'gestor_password' => 'nullable|string|min:8',
        ], [
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'gestor_email.unique' => 'Este e-mail do gestor já está em uso.',
        ]);

        $autoPassword = ! $request->filled('gestor_password');
        $plainPassword = $autoPassword ? Str::password(12, symbols: true) : $request->gestor_password;

        $company = null;

        DB::transaction(function () use ($validated, $plainPassword, &$company) {
            $company = Company::create([
                'name' => $validated['name'],
                'cnpj' => $validated['cnpj'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'zipcode' => $validated['zipcode'] ?? null,
                'active' => true,
                'require_photo' => true,
                'require_geolocation' => false,
            ]);

            $gestor = User::create([
                'name' => $validated['gestor_name'],
                'email' => $validated['gestor_email'],
                'password' => Hash::make($plainPassword),
                'role' => 'gestor',
                'active' => true,
                'company_id' => $company->id,
            ]);

            // O role Spatie sanctum é atribuído automaticamente no primeiro login via API móvel.
        });

        $redirect = redirect()
            ->route('painel.companies.show', $company)
            ->with('success', 'Empresa criada. O gestor pode entrar no aplicativo móvel com o e-mail e a palavra-passe indicados.');

        if ($autoPassword) {
            $redirect->with('gestor_password_plain', $plainPassword);
        }

        return $redirect;
    }

    public function show(Company $company, Request $request): View
    {
        $this->authorize('manage-companies');

        $company->loadCount('activeEmployees');
        $gestores = $company->users()->where('role', 'gestor')->orderBy('name')->get();
        $totems   = $company->users()->where('role', 'totem')->orderBy('name')->get();
        // tab activa vinda do redirect (ex: após guardar dados redireciona para ?tab=dados)
        $activeTab = $request->get('tab', 'dados');

        return view('web.companies.show', compact('company', 'gestores', 'totems', 'activeTab'));
    }

    public function edit(Company $company): \Illuminate\Http\RedirectResponse
    {
        // Redireciona para show (página unificada)
        return redirect()->route('painel.companies.show', $company);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('manage-companies');

        $request->validate([
            'name' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18|unique:companies,cnpj,'.$company->id,
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zipcode' => 'nullable|string|max:10',
            'ibge_code' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:50|max:5000',
            'require_photo' => 'nullable|boolean',
            'require_geolocation' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'work_start' => 'nullable|date_format:H:i',
            'work_end' => 'nullable|date_format:H:i',
            'lunch_duration' => 'nullable|integer|min:0|max:120',
            'max_daily_records' => 'nullable|integer|min:2|max:20',
        ]);

        $company->update([
            'name' => $request->name,
            'cnpj' => $request->cnpj,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zipcode' => $request->zipcode,
            'ibge_code' => $request->ibge_code ?: null,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'geofence_radius' => $request->geofence_radius,
            'require_photo' => $request->boolean('require_photo'),
            'require_geolocation' => $request->boolean('require_geolocation'),
            'active' => $request->boolean('active'),
            'work_start' => $request->work_start,
            'work_end' => $request->work_end,
            'lunch_duration' => $request->lunch_duration,
            'max_daily_records' => $request->integer('max_daily_records') ?: 10,
        ]);

        AuditService::log(
            $request->user(),
            'company.update',
            'Empresa actualizada: '.$company->name,
            $company,
            null,
            $company->id,
            $request
        );

        return redirect()
            ->route('painel.companies.show', array_merge(['company' => $company->id], ['tab' => 'dados']))
            ->with('success', 'Empresa atualizada.');
    }

    public function updateGestor(Request $request, Company $company, User $gestor): RedirectResponse
    {
        $this->authorize('manage-companies');

        abort_if($gestor->company_id !== $company->id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $gestor->id,
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $gestor->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'gestores'])
            ->with('success', 'Dados do gestor actualizados.');
    }

    public function resetGestorPassword(Request $request, Company $company, User $gestor): RedirectResponse
    {
        $this->authorize('manage-companies');

        abort_if($gestor->company_id !== $company->id, 403);

        $request->validate([
            'password' => 'nullable|string|min:8',
        ]);

        $autoPassword = ! $request->filled('password');
        $plain = $autoPassword ? Str::password(12, symbols: true) : $request->password;

        $gestor->update(['password' => Hash::make($plain)]);

        $redirect = redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'gestores'])
            ->with('success', 'Palavra-passe do gestor redefinida.');

        if ($autoPassword) {
            $redirect->with('gestor_password_plain', $plain);
        }

        return $redirect;
    }

    public function addGestor(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('manage-companies');

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $autoPassword = ! $request->filled('password');
        $plain = $autoPassword ? Str::password(12, symbols: true) : $request->password;

        User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($plain),
            'role'       => 'gestor',
            'active'     => true,
            'company_id' => $company->id,
        ]);

        $redirect = redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'gestores'])
            ->with('success', 'Novo gestor adicionado.');

        if ($autoPassword) {
            $redirect->with('gestor_password_plain', $plain);
        }

        return $redirect;
    }

    // ─── Totem ───────────────────────────────────────────────────────────────

    public function addTotem(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('manage-companies');

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
        ], [
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $autoPassword = ! $request->filled('password');
        $plain = $autoPassword ? Str::password(14, symbols: false) : $request->password;

        User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($plain),
            'role'       => 'totem',
            'active'     => true,
            'company_id' => $company->id,
        ]);

        $redirect = redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'totems'])
            ->with('success', 'Dispositivo totem criado.');

        if ($autoPassword) {
            $redirect->with('totem_password_plain', $plain);
        }

        return $redirect;
    }

    public function toggleTotem(Company $company, User $totem): RedirectResponse
    {
        $this->authorize('manage-companies');
        abort_if($totem->company_id !== $company->id || $totem->role !== 'totem', 403);

        $totem->update(['active' => ! $totem->active]);

        return redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'totems'])
            ->with('success', $totem->active ? 'Totem reativado.' : 'Totem desativado.');
    }

    public function resetTotemPassword(Request $request, Company $company, User $totem): RedirectResponse
    {
        $this->authorize('manage-companies');
        abort_if($totem->company_id !== $company->id || $totem->role !== 'totem', 403);

        $autoPassword = ! $request->filled('password');
        $plain = $autoPassword ? Str::password(14, symbols: false) : $request->password;

        $totem->update(['password' => Hash::make($plain)]);

        $redirect = redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'totems'])
            ->with('success', 'Senha do totem redefinida.');

        if ($autoPassword) {
            $redirect->with('totem_password_plain', $plain);
        }

        return $redirect;
    }
}
