<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $search = $request->get('q');
        $role   = $request->get('role');

        $users = User::withCount('employee')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->when($role, fn($q) => $q->where('role', $role))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('web.users.index', compact('users', 'search', 'role'));
    }

    public function create(): View
    {
        $this->authorize('manage-employees');
        return view('web.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:admin,gestor',
        ], [
            'email.unique'          => 'Este e-mail já está em uso.',
            'password.confirmed'    => 'As senhas não coincidem.',
            'password.min'          => 'A senha deve ter pelo menos 6 caracteres.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'active'   => true,
        ]);
        $user->assignRole(\Spatie\Permission\Models\Role::findByName($request->role, 'sanctum'));

        return redirect()->route('painel.users.index')
            ->with('success', 'Utilizador criado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->authorize('manage-employees');
        return view('web.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . $user->id,
            'role'   => 'required|in:admin,gestor,funcionario',
            'active' => 'boolean',
        ]);

        $user->update([
            'name'   => $request->name,
            'email'  => $request->email,
            'role'   => $request->role,
            'active' => $request->boolean('active'),
        ]);

        $user->syncRoles([\Spatie\Permission\Models\Role::findByName($request->role, 'sanctum')]);

        return redirect()->route('painel.users.index')
            ->with('success', 'Utilizador atualizado.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'As senhas não coincidem.',
            'password.min'       => 'A senha deve ter pelo menos 6 caracteres.',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Senha redefinida com sucesso.');
    }
}
