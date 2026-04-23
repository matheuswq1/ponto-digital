@extends('web.layout')
@section('title', 'Novo Utilizador')
@section('page-title', 'Novo Utilizador')

@section('content')

<div class="max-w-lg">

@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="post" action="{{ route('painel.users.store') }}" class="space-y-5">
    @csrf

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nome completo <span class="text-rose-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">E-mail <span class="text-rose-500">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Role <span class="text-rose-500">*</span></label>
            <select name="role" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                <option value="admin"  @selected(old('role')=='admin')>Administrador</option>
                <option value="gestor" @selected(old('role')=='gestor')>Gestor de RH</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Senha <span class="text-rose-500">*</span></label>
            <input type="password" name="password" required minlength="6"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Confirmar senha <span class="text-rose-500">*</span></label>
            <input type="password" name="password_confirmation" required minlength="6"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">
            Criar utilizador
        </button>
        <a href="{{ route('painel.users.index') }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
</div>

@endsection
