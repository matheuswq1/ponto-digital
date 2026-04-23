@extends('web.layout')
@section('title', 'Editar Utilizador')
@section('page-title', 'Editar Utilizador')

@section('content')

<div class="max-w-lg space-y-5">

@if(session('success'))
<div class="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Editar dados --}}
<form method="post" action="{{ route('painel.users.update', $user) }}">
    @csrf @method('PUT')
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700">Dados do utilizador</h2>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nome completo</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">E-mail</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Role</label>
            <select name="role" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                <option value="admin"       @selected(old('role', $user->role)=='admin')>Administrador</option>
                <option value="gestor"      @selected(old('role', $user->role)=='gestor')>Gestor de RH</option>
                <option value="funcionario" @selected(old('role', $user->role)=='funcionario')>Colaborador</option>
            </select>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true))
                       class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                <span class="text-sm text-slate-700">Conta ativa</span>
            </label>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg hover:bg-indigo-700 transition">Salvar</button>
            <a href="{{ route('painel.users.index') }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>
        </div>
    </div>
</form>

{{-- Redefinir senha --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h2 class="text-sm font-semibold text-slate-700 mb-4">Redefinir senha</h2>
    <form method="post" action="{{ route('painel.users.reset-password', $user) }}" class="space-y-4">
        @csrf @method('PATCH')
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nova senha</label>
            <input type="password" name="password" required minlength="6"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Confirmar senha</label>
            <input type="password" name="password_confirmation" required minlength="6"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <button type="submit" class="text-sm font-medium px-5 py-2 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 transition">
            Redefinir senha
        </button>
    </form>
</div>

</div>
@endsection
