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
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                    Contato para notificação
                    <span class="ml-1 inline-flex items-center gap-1 text-emerald-600 font-semibold">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.117.554 4.103 1.523 5.83L.057 23.077a.75.75 0 0 0 .916.932l5.404-1.41A11.955 11.955 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.71 9.71 0 0 1-4.953-1.357l-.355-.21-3.684.961.986-3.6-.23-.37A9.697 9.697 0 0 1 2.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/></svg>
                        WhatsApp
                    </span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-sm">+55</span>
                    <input type="text" name="notification_contact" value="{{ old('notification_contact') }}"
                           placeholder="11999999999"
                           maxlength="20"
                           class="w-full text-sm border border-slate-300 rounded-lg pl-10 pr-3 py-2 focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none">
                </div>
                <p class="text-xs text-slate-400 mt-1">Número que receberá alertas de solicitações de ponto (somente dígitos, com DDD).</p>
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
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                    Código IBGE do município
                    <span class="text-slate-400 font-normal ml-1">— para feriados regionais</span>
                </label>
                <input type="text" name="ibge_code" value="{{ old('ibge_code') }}"
                       maxlength="10" placeholder="Ex: 3550308 (São Paulo/SP)"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Consulte em
                    <a href="https://servicodados.ibge.gov.br/api/v1/localidades/municipios" target="_blank" class="text-indigo-500 hover:underline">ibge.gov.br</a>.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-1">Acesso ao aplicativo (gestor)</h2>
        <p class="text-xs text-slate-500 mb-4">Será criado um utilizador <strong>gestor</strong> ligado a esta empresa. Use estas credenciais no telemóvel ou tablet com o app RM Colaboradores.</p>
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
