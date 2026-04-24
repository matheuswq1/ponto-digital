@extends('web.layout')
@section('title', $company->name)
@section('page-title', $company->name)

@section('content')

{{-- Flash --}}
@if(session('success'))
<div class="mb-4 flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    <div>
        <p>{{ session('success') }}</p>
        @if(session('gestor_password_plain'))
            <p class="mt-2 font-mono text-xs bg-white/60 rounded px-2 py-1 border border-emerald-200">Senha do gestor: <strong>{{ session('gestor_password_plain') }}</strong></p>
            <p class="mt-1 text-xs text-emerald-800/80">Guarde esta senha — não será mostrada novamente.</p>
        @endif
        @if(session('totem_password_plain'))
            <p class="mt-2 font-mono text-xs bg-white/60 rounded px-2 py-1 border border-emerald-200">Senha do totem: <strong>{{ session('totem_password_plain') }}</strong></p>
            <p class="mt-1 text-xs text-emerald-800/80">Guarde esta senha — não será mostrada novamente.</p>
        @endif
    </div>
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- Breadcrumb --}}
<div class="flex items-center gap-2 mb-5 text-sm">
    <a href="{{ route('painel.companies.index') }}" class="text-slate-500 hover:text-slateigo-600 hover:underline">← Empresas</a>
    <span class="text-slate-300">/</span>
    <span class="text-slate-700 font-medium">{{ $company->name }}</span>
    @if(!$company->active)
        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inativa</span>
    @endif
</div>

{{-- Página unificada com tabs Alpine.js --}}
<div x-data="{ tab: '{{ $activeTab }}' }">

    {{-- Nav de tabs --}}
    <div class="flex gap-1 bg-slate-100 rounded-xl p-1 mb-6 max-w-md">
        <button type="button" @click="tab='dados'"
                :class="tab==='dados' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
            </svg>
            Dados
        </button>
        <button type="button" @click="tab='gestores'"
                :class="tab==='gestores' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            Gestores <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 rounded-full px-1.5 py-0.5">{{ $gestores->count() }}</span>
        </button>
        <button type="button" @click="tab='totems'"
                :class="tab==='totems' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
            </svg>
            Totems <span class="ml-1 text-xs bg-violet-100 text-violet-700 rounded-full px-1.5 py-0.5">{{ $totems->count() }}</span>
        </button>
    </div>

    {{-- ══════════════════════════════ TAB DADOS ══════════════════════════════ --}}
    <div x-show="tab==='dados'" x-cloak>
        <form method="post" action="{{ route('painel.companies.update', $company) }}" class="space-y-5 max-w-3xl">
            @csrf

            {{-- Dados gerais --}}
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
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
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
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Código IBGE
                            <span class="text-slate-400 font-normal ml-1">— feriados regionais</span>
                        </label>
                        <input type="text" name="ibge_code" value="{{ old('ibge_code', $company->ibge_code) }}"
                               maxlength="10" placeholder="Ex: 3550308"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                        @if($company->ibge_code)
                            <p class="text-[11px] text-emerald-600 mt-1">✓ Feriados municipais configurados</p>
                        @else
                            <p class="text-[11px] text-slate-400 mt-1">Sem código IBGE — só feriados nacionais e estaduais</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Geofence --}}
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
                    <div class="flex flex-col gap-3 pt-1">
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

            {{-- Horário de referência --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-700 mb-4">Horário de referência</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
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
                    </div>
                </div>
            </div>

            {{-- Info rápida --}}
            <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 text-sm text-slate-600 flex items-center gap-6">
                <span><strong class="text-slate-800">{{ $company->active_employees_count }}</strong> colaboradores ativos</span>
                @if($company->ibge_code)
                    <span class="text-emerald-600">✓ Feriados municipais: IBGE {{ $company->ibge_code }}</span>
                @endif
                @if($company->holidays_synced_at)
                    <span class="text-xs text-slate-400">Sync: {{ $company->holidays_synced_at->format('d/m/Y') }}</span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">
                    Guardar alterações
                </button>
            </div>
        </form>
    </div>

    {{-- ══════════════════════════════ TAB GESTORES ══════════════════════════════ --}}
    <div x-show="tab==='gestores'" x-cloak class="max-w-2xl space-y-4">

        @forelse($gestores as $g)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" x-data="{ tab: 'dados' }">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm shrink-0">
                    {{ strtoupper(substr($g->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-slate-800 truncate">{{ $g->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $g->email }}</p>
                </div>
                <span class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">Gestor</span>
            </div>

            <div class="flex gap-1 bg-slate-100 rounded-lg p-0.5 mb-4">
                <button type="button" @click="tab='dados'"
                        :class="tab==='dados' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Editar dados</button>
                <button type="button" @click="tab='senha'"
                        :class="tab==='senha' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Redefinir senha</button>
            </div>

            <div x-show="tab==='dados'">
                <form method="post" action="{{ route('painel.companies.gestores.update', [$company, $g]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nome</label>
                        <input type="text" name="name" value="{{ old('name', $g->name) }}" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">E-mail de login</label>
                        <input type="email" name="email" value="{{ old('email', $g->email) }}" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none">
                    </div>
                    <button type="submit" class="w-full text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Guardar
                    </button>
                </form>
            </div>

            <div x-show="tab==='senha'">
                <form method="post" action="{{ route('painel.companies.gestores.password', [$company, $g]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nova senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               placeholder="Deixe em branco para gerar automaticamente"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-200 outline-none">
                    </div>
                    <button type="submit" class="w-full text-sm font-medium text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg hover:bg-amber-100 transition">
                        Redefinir senha
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-6 text-sm text-slate-500 text-center">
            Nenhum gestor registado nesta empresa.
        </div>
        @endforelse

        {{-- Adicionar gestor --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-100 p-5" x-data="{ open: false }">
            <button type="button" @click="open=!open"
                    class="flex items-center gap-2 text-sm font-medium text-indigo-700 hover:text-indigo-900 w-full">
                <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Adicionar novo gestor
            </button>
            <div x-show="open" x-cloak class="mt-4 space-y-3">
                <form method="post" action="{{ route('painel.companies.gestores.add', $company) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">E-mail <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               placeholder="Deixe em branco para gerar automaticamente"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                    </div>
                    <button type="submit" class="w-full text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Criar gestor
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════ TAB TOTEMS ══════════════════════════════ --}}
    <div x-show="tab==='totems'" x-cloak class="max-w-2xl space-y-4">

        @forelse($totems as $t)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" x-data="{ tab: 'senha' }">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 text-violet-700 shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-slate-800 truncate">{{ $t->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $t->email }}</p>
                </div>
                @if($t->active)
                    <span class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">Ativo</span>
                @else
                    <span class="text-xs text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5">Inativo</span>
                @endif
            </div>

            <div class="flex gap-1 bg-slate-100 rounded-lg p-0.5 mb-4">
                <button type="button" @click="tab='senha'"
                        :class="tab==='senha' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Redefinir senha</button>
                <button type="button" @click="tab='status'"
                        :class="tab==='status' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Ativar / Desativar</button>
            </div>

            <div x-show="tab==='senha'">
                <form method="post" action="{{ route('painel.companies.totems.password', [$company, $t]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nova senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               placeholder="Deixe em branco para gerar automaticamente"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none">
                    </div>
                    <button type="submit" class="w-full text-sm font-medium text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg hover:bg-amber-100 transition">
                        Redefinir senha
                    </button>
                </form>
            </div>

            <div x-show="tab==='status'">
                <form method="post" action="{{ route('painel.companies.totems.toggle', [$company, $t]) }}">
                    @csrf
                    <p class="text-sm text-slate-600 mb-3">
                        Status actual: <strong>{{ $t->active ? 'Ativo' : 'Inativo' }}</strong>
                    </p>
                    <button type="submit"
                            class="w-full text-sm font-medium px-3 py-2 rounded-lg transition
                                   {{ $t->active
                                       ? 'text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100'
                                       : 'text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100' }}">
                        {{ $t->active ? 'Desativar totem' : 'Reativar totem' }}
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-6 text-sm text-slate-500 text-center">
            Nenhum totem registado. Adicione um abaixo.
        </div>
        @endforelse

        {{-- Adicionar totem --}}
        <div class="bg-violet-50 rounded-xl border border-violet-100 p-5" x-data="{ open: false }">
            <button type="button" @click="open=!open"
                    class="flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-full">
                <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Adicionar novo totem
            </button>
            <div x-show="open" x-cloak class="mt-4 space-y-3">
                <form method="post" action="{{ route('painel.companies.totems.add', $company) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nome do dispositivo <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required placeholder="Ex: Totem Recepção"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">E-mail de login <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               placeholder="Deixe em branco para gerar automaticamente"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                    </div>
                    <button type="submit" class="w-full text-sm font-medium text-white bg-violet-600 px-3 py-2 rounded-lg hover:bg-violet-700 transition">
                        Criar totem
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endsection
