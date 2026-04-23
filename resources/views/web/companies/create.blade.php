@extends('web.layout')
@section('title', 'Nova empresa')
@section('page-title', 'Nova empresa')

@section('content')

<div class="max-w-3xl">

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

<form method="post" action="{{ route('painel.companies.store') }}" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Dados da empresa</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Razão social / nome <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CNPJ <span class="text-rose-500">*</span></label>
                <input type="text" name="cnpj" value="{{ old('cnpj') }}" required maxlength="18"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Telefone</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail da empresa</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Morada</label>
                <input type="text" name="address" value="{{ old('address') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cidade</label>
                <input type="text" name="city" value="{{ old('city') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">UF</label>
                <input type="text" name="state" value="{{ old('state') }}" maxlength="2" placeholder="SP"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none uppercase">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CEP</label>
                <input type="text" name="zipcode" value="{{ old('zipcode') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-1">Acesso ao aplicativo (gestor)</h2>
        <p class="text-xs text-slate-500 mb-4">Será criado um utilizador <strong>gestor</strong> ligado a esta empresa. Use estas credenciais no telemóvel ou tablet com o app Ponto Digital.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Nome do gestor <span class="text-rose-500">*</span></label>
                <input type="text" name="gestor_name" value="{{ old('gestor_name') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail de login (gestor) <span class="text-rose-500">*</span></label>
                <input type="email" name="gestor_email" value="{{ old('gestor_email') }}" required autocomplete="off"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Palavra-passe inicial</label>
                <input type="password" name="gestor_password" minlength="8" autocomplete="new-password"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none"
                       placeholder="Mínimo 8 caracteres; deixe em branco para gerar automaticamente">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">Criar empresa</button>
        <a href="{{ route('painel.companies.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
    </div>
</form>

</div>

@endsection
