@extends('web.layout')
@section('title', 'Editar — '.$company->name)
@section('page-title', 'Editar empresa')

@section('content')

<div class="max-w-3xl">

@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="post" action="{{ route('painel.companies.update', $company) }}" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Dados gerais</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CNPJ <span class="text-rose-500">*</span></label>
                <input type="text" name="cnpj" value="{{ old('cnpj', $company->cnpj) }}" required maxlength="18"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 mt-6">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('active', $company->active))>
                    Empresa ativa
                </label>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $company->email) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Telefone</label>
                <input type="text" name="phone" value="{{ old('phone', $company->phone) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
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
                    <input type="text" name="notification_contact" value="{{ old('notification_contact', $company->notification_contact) }}"
                           placeholder="11999999999"
                           maxlength="20"
                           class="w-full text-sm border border-slate-300 rounded-lg pl-10 pr-3 py-2 focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none">
                </div>
                <p class="text-xs text-slate-400 mt-1">Número que receberá alertas de solicitações de ponto (somente dígitos, com DDD).</p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Morada</label>
                <input type="text" name="address" value="{{ old('address', $company->address) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cidade</label>
                <input type="text" name="city" value="{{ old('city', $company->city) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">UF</label>
                <input type="text" name="state" value="{{ old('state', $company->state) }}" maxlength="2"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 uppercase focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CEP</label>
                <input type="text" name="zipcode" value="{{ old('zipcode', $company->zipcode) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                    Código IBGE do município
                    <span class="text-slate-400 font-normal ml-1">— para feriados regionais</span>
                </label>
                <input type="text" name="ibge_code" value="{{ old('ibge_code', $company->ibge_code) }}"
                       maxlength="10" placeholder="Ex: 3550308 (São Paulo/SP)"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                <p class="text-[11px] text-slate-400 mt-1">
                    Consulte em
                    <a href="https://servicodados.ibge.gov.br/api/v1/localidades/municipios" target="_blank" class="text-indigo-500 hover:underline">ibge.gov.br</a>
                    ou pesquise pelo nome do município.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Geofence e requisitos de ponto</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Latitude</label>
                <input type="text" name="latitude" value="{{ old('latitude', $company->latitude) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Longitude</label>
                <input type="text" name="longitude" value="{{ old('longitude', $company->longitude) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Raio (m)</label>
                <input type="number" name="geofence_radius" value="{{ old('geofence_radius', $company->geofence_radius) }}" min="50" max="5000"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div class="flex flex-col gap-3 pt-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="require_photo" value="0">
                    <input type="checkbox" name="require_photo" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('require_photo', $company->require_photo))>
                    Exigir foto no ponto
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="require_geolocation" value="0">
                    <input type="checkbox" name="require_geolocation" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('require_geolocation', $company->require_geolocation))>
                    Exigir localização
                </label>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Horário de referência</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Entrada</label>
                <input type="time" name="work_start" value="{{ old('work_start', $company->work_start ? substr((string) $company->work_start, 0, 5) : '') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Saída</label>
                <input type="time" name="work_end" value="{{ old('work_end', $company->work_end ? substr((string) $company->work_end, 0, 5) : '') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Almoço (min)</label>
                <input type="number" name="lunch_duration" value="{{ old('lunch_duration', $company->lunch_duration) }}" min="0" max="120"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Máx. batidas/dia</label>
                <input type="number" name="max_daily_records" value="{{ old('max_daily_records', $company->max_daily_records ?? 10) }}" min="2" max="20"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                <p class="text-xs text-slate-400 mt-1">Limite de batidas de ponto por funcionário por dia (padrão: 10)</p>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Modo de tolerância (banco de horas)</label>
                <x-tolerance-mode-select name="tolerance_mode" :value="$company->tolerance_mode ?? 'daily_dead_band'"
                    hint="Faixa neutra: dentro de ±tolerância o saldo do dia fica zero; fora conta o desvio inteiro. Desconto: reduz o desvio pelos minutos de tolerância." />
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Fuso horário da empresa</label>
                <x-company-timezone-select name="timezone" :value="$company->timezone" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">Guardar</button>
        <a href="{{ route('painel.companies.show', $company) }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
    </div>
</form>

</div>

@endsection
