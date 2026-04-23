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
    @method('PUT')

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
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">Guardar</button>
        <a href="{{ route('painel.companies.show', $company) }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
    </div>
</form>

</div>

@endsection
