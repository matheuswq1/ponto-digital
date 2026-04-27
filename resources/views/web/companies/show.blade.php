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
    <div class="flex flex-wrap gap-1 bg-slate-100 rounded-xl p-1 mb-6 max-w-2xl">
        <button type="button" @click="tab='dados'"
                :class="tab==='dados' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition min-w-[80px]">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>
            </svg>
            Dados
        </button>
        <button type="button" @click="tab='localizacoes'"
                :class="tab==='localizacoes' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition min-w-[100px]">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
            </svg>
            Localizações <span class="ml-1 text-xs bg-emerald-100 text-emerald-700 rounded-full px-1.5 py-0.5">{{ $locations->count() }}</span>
        </button>
        <button type="button" @click="tab='gestores'"
                :class="tab==='gestores' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition min-w-[80px]">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            Gestores <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 rounded-full px-1.5 py-0.5">{{ $gestores->count() }}</span>
        </button>
        <button type="button" @click="tab='totems'"
                :class="tab==='totems' ? 'bg-white shadow text-slate-800 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 flex items-center justify-center gap-2 text-sm py-2 rounded-lg transition min-w-[80px]">
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

            {{-- Anti-fraude --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6" x-data="{ requireWifi: {{ old('require_wifi', $company->require_wifi) ? 'true' : 'false' }}, blockVelocity: {{ old('block_velocity_jump', $company->block_velocity_jump) ? 'true' : 'false' }} }">
                <h2 class="text-sm font-semibold text-slate-700 mb-1">Segurança / Anti-fraude</h2>
                <p class="text-xs text-slate-400 mb-4">Cada regra pode ser activada/desactivada de forma independente. A acção global define se bloqueia o ponto ou apenas regista o alerta.</p>

                <div class="space-y-4">
                    {{-- Acção global --}}
                    <div class="bg-slate-50 rounded-lg px-4 py-3 flex flex-wrap items-center gap-4 border border-slate-200">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-700">Acção quando fraude for detectada</p>
                            <p class="text-xs text-slate-400">Bloquear impede o ponto; Apenas avisar regista e notifica o admin.</p>
                        </div>
                        <select name="fraud_action" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-200 outline-none bg-white">
                            <option value="warn"  @selected(old('fraud_action', $company->fraud_action ?? 'warn')  === 'warn')>Apenas avisar</option>
                            <option value="block" @selected(old('fraud_action', $company->fraud_action ?? 'warn') === 'block')>Bloquear o ponto</option>
                        </select>
                    </div>

                    {{-- GPS Falso --}}
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <div class="mt-0.5">
                            <input type="hidden" name="block_mock_location" value="0">
                            <input type="checkbox" name="block_mock_location" value="1" class="rounded border-slate-300 text-rose-500 mt-0.5"
                                @checked(old('block_mock_location', $company->block_mock_location))>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-700">Detectar GPS Falso (Mock Location)</p>
                            <p class="text-xs text-slate-400">Flutter reporta `is_mock_location=1` quando apps de GPS falso estão activos. Requer que o colaborador utilize o app original.</p>
                        </div>
                    </label>

                    {{-- Salto de Velocidade --}}
                    <div class="p-3 rounded-lg border border-slate-200">
                        <label class="flex items-start gap-3 cursor-pointer" @click="blockVelocity = !blockVelocity">
                            <div class="mt-0.5">
                                <input type="hidden" name="block_velocity_jump" value="0">
                                <input type="checkbox" name="block_velocity_jump" value="1" x-model="blockVelocity" class="rounded border-slate-300 text-rose-500">
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Detectar salto de localização suspeito</p>
                                <p class="text-xs text-slate-400">Ex.: colaborador em SP e 2 segundos depois noutro estado — velocidade impossível é sinal de fraude.</p>
                            </div>
                        </label>
                        <div x-show="blockVelocity" x-cloak class="mt-3 flex items-center gap-3 pl-7">
                            <label class="text-xs font-medium text-slate-600 whitespace-nowrap">Velocidade máxima (km/h)</label>
                            <input type="number" name="velocity_jump_threshold_kmh"
                                   value="{{ old('velocity_jump_threshold_kmh', $company->velocity_jump_threshold_kmh ?? 300) }}"
                                   min="10" max="2000"
                                   class="w-28 text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-rose-200 outline-none">
                            <span class="text-xs text-slate-400">300 = padrão (carro rápido; avião detectado)</span>
                        </div>
                    </div>

                    {{-- Wi-Fi --}}
                    <div class="p-3 rounded-lg border border-slate-200">
                        <label class="flex items-start gap-3 cursor-pointer" @click="requireWifi = !requireWifi">
                            <div class="mt-0.5">
                                <input type="hidden" name="require_wifi" value="0">
                                <input type="checkbox" name="require_wifi" value="1" x-model="requireWifi" class="rounded border-slate-300 text-rose-500">
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Exigir rede Wi-Fi específica</p>
                                <p class="text-xs text-slate-400">O app verifica o SSID da rede conectada e compara com a lista abaixo (apenas Android/iOS).</p>
                            </div>
                        </label>
                        <div x-show="requireWifi" x-cloak class="mt-3 pl-7 space-y-1">
                            <label class="text-xs font-medium text-slate-600">SSIDs autorizados <span class="text-slate-400 font-normal">(um por linha)</span></label>
                            <textarea name="allowed_wifi_ssids_raw" rows="3"
                                      placeholder="MinhaRedeEmpresa&#10;MinhaRedeEmpresa_5G"
                                      class="w-full text-sm font-mono border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-200 outline-none resize-none">{{ old('allowed_wifi_ssids_raw', implode("\n", $company->allowed_wifi_ssids ?? [])) }}</textarea>
                            <p class="text-[11px] text-slate-400">Sensível a maiúsculas/minúsculas. Deixe vazio para bloquear qualquer Wi-Fi desconhecido.</p>
                        </div>
                    </div>

                    {{-- Cidade do IP --}}
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <div class="mt-0.5">
                            <input type="hidden" name="block_unknown_ip_city" value="0">
                            <input type="checkbox" name="block_unknown_ip_city" value="1" class="rounded border-slate-300 text-rose-500"
                                @checked(old('block_unknown_ip_city', $company->block_unknown_ip_city))>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-700">Verificar cidade do endereço IP</p>
                            <p class="text-xs text-slate-400">Se o IP de origem for de cidade diferente da empresa (campo Cidade acima), gera alerta. Requer que a "Cidade" da empresa esteja preenchida.</p>
                        </div>
                    </label>
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

    {{-- ══════════════════════════════ TAB LOCALIZAÇÕES ══════════════════════════ --}}
    <div x-show="tab==='localizacoes'" x-cloak class="space-y-5 max-w-4xl">

        @if(session('success') && session()->has('locations_tab'))
        <div class="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            {{ session('success') }}
        </div>
        @endif
        @if(session('error') && session()->has('locations_tab'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
        @endif

        {{-- Mapa de visão geral --}}
        @if($googleMapsKey && $locations->where('active', true)->count() > 0)
        @php
            $mapCenter = $locations->where('active', true)->first();
        @endphp
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                <h2 class="text-sm font-semibold text-slate-700">Mapa das geocercas</h2>
                <span class="ml-auto text-xs text-slate-400">{{ $locations->where('active', true)->count() }} localização(ões) ativa(s)</span>
            </div>
            <div id="locations-map" class="w-full" style="height: 320px;"></div>
        </div>
        @endif

        {{-- Lista de localizações --}}
        @forelse($locations as $loc)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" x-data="{ editing: false }">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-sm font-semibold text-slate-800">{{ $loc->name }}</span>
                        @if($loc->active)
                            <span class="inline-flex text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full px-2 py-0.5">Ativa</span>
                        @else
                            <span class="inline-flex text-xs font-medium bg-slate-100 text-slate-500 rounded-full px-2 py-0.5">Inativa</span>
                        @endif
                    </div>
                    @if($loc->address)
                    <p class="text-xs text-slate-500 mb-1">{{ $loc->address }}</p>
                    @endif
                    <p class="text-xs text-slate-400 font-mono">
                        {{ number_format($loc->latitude, 6) }}, {{ number_format($loc->longitude, 6) }}
                        &nbsp;·&nbsp; raio: {{ $loc->radius_meters }}m
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="https://maps.google.com/?q={{ $loc->latitude }},{{ $loc->longitude }}"
                       target="_blank" class="text-xs text-indigo-600 hover:underline">Ver no Maps</a>
                    <button type="button" @click="editing=!editing"
                            class="text-xs text-slate-600 border border-slate-300 bg-white px-2 py-1 rounded-lg hover:bg-slate-50">
                        Editar
                    </button>
                    <form method="post" action="{{ route('painel.companies.locations.destroy', [$company, $loc]) }}"
                          onsubmit="return confirm('Remover esta localização?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-rose-600 border border-rose-200 bg-rose-50 px-2 py-1 rounded-lg hover:bg-rose-100">
                            Remover
                        </button>
                    </form>
                </div>
            </div>

            {{-- Formulário de edição --}}
            <div x-show="editing" x-cloak class="mt-4 border-t border-slate-100 pt-4">
                <form method="post" action="{{ route('painel.companies.locations.update', [$company, $loc]) }}" class="space-y-3"
                      x-data="locationForm('{{ $loc->address ?? '' }}', {{ $loc->latitude }}, {{ $loc->longitude }})"
                      @submit.prevent="submitForm($el)">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" value="{{ $loc->name }}" required
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Raio (metros) <span class="text-rose-500">*</span></label>
                            <input type="number" name="radius_meters" value="{{ $loc->radius_meters }}" min="50" max="50000" required
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Endereço (pesquise para geocodificar)</label>
                            <div class="flex gap-2">
                                <input type="text" name="address" x-model="address" placeholder="Rua, número, cidade..."
                                       class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none">
                                <button type="button" @click="geocode()"
                                        class="text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 whitespace-nowrap">
                                    Buscar
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1" x-text="geoStatus"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Latitude</label>
                            <input type="text" name="latitude" x-model="lat"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Longitude</label>
                            <input type="text" name="longitude" x-model="lng"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none font-mono">
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" id="active_{{ $loc->id }}" class="rounded border-slate-300 text-emerald-500" @checked($loc->active)>
                            <label for="active_{{ $loc->id }}" class="text-sm text-slate-700">Localização ativa</label>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="text-sm font-medium text-white bg-emerald-600 px-4 py-2 rounded-lg hover:bg-emerald-700">Guardar</button>
                        <button type="button" @click="editing=false" class="text-sm text-slate-600 px-3 py-2 hover:bg-slate-100 rounded-lg">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-slate-50 rounded-xl border border-slate-200 p-8 text-center">
            <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
            <p class="text-sm text-slate-500">Nenhuma localização configurada.</p>
            <p class="text-xs text-slate-400 mt-1">Adicione pelo menos uma localização para activar a geocerca.</p>
        </div>
        @endforelse

        {{-- Adicionar nova localização --}}
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-5" x-data="{ open: false }">
            <button type="button" @click="open=!open"
                    class="flex items-center gap-2 text-sm font-medium text-emerald-700 hover:text-emerald-900 w-full">
                <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Adicionar nova localização
            </button>
            <div x-show="open" x-cloak class="mt-4"
                 x-data="locationForm('', null, null)">
                <form method="post" action="{{ route('painel.companies.locations.store', $company) }}"
                      class="space-y-3" @submit.prevent="submitForm($el)">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required placeholder="Ex.: Sede, Filial Norte"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Raio (metros) <span class="text-rose-500">*</span></label>
                            <input type="number" name="radius_meters" value="300" min="50" max="50000" required
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Endereço (pesquise para geocodificar automaticamente)</label>
                            <div class="flex gap-2">
                                <input type="text" name="address" x-model="address" placeholder="Rua, número, cidade, estado..."
                                       class="flex-1 text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none bg-white">
                                <button type="button" @click="geocode()"
                                        class="text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 whitespace-nowrap">
                                    Buscar
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1" x-text="geoStatus"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Latitude</label>
                            <input type="text" name="latitude" x-model="lat" placeholder="Preenchido automaticamente"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none bg-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Longitude</label>
                            <input type="text" name="longitude" x-model="lng" placeholder="Preenchido automaticamente"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 outline-none bg-white font-mono">
                        </div>
                    </div>
                    <button type="submit" class="text-sm font-medium text-white bg-emerald-600 px-5 py-2.5 rounded-lg hover:bg-emerald-700 transition">
                        Adicionar localização
                    </button>
                </form>
            </div>
        </div>

        {{-- Nota sobre geocerca legada --}}
        @if($company->hasLegacyGeofence() && $locations->count() > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
            <strong>Nota:</strong> A empresa ainda tem coordenadas legadas (Latitude/Longitude na aba Dados). Com localizações configuradas aqui, o sistema usa apenas estas. Pode limpar os campos legados na aba Dados se não forem necessários.
        </div>
        @endif
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

{{-- Google Maps + lógica de geocodificação --}}
<script>
// Alpine component para formulários de localização
document.addEventListener('alpine:init', () => {
  Alpine.data('locationForm', (initAddress, initLat, initLng) => ({
    address: initAddress || '',
    lat: initLat || '',
    lng: initLng || '',
    geoStatus: '',
    async geocode() {
      if (!this.address.trim()) { this.geoStatus = 'Preencha o endereço.'; return; }
      this.geoStatus = 'A pesquisar…';
      try {
        const r = await fetch('{{ route('painel.companies.geocode', $company) }}?address=' + encodeURIComponent(this.address));
        const d = await r.json();
        if (d.error) { this.geoStatus = d.error; return; }
        this.lat = d.lat;
        this.lng = d.lng;
        this.address = d.formatted_address;
        this.geoStatus = '✓ Coordenadas encontradas: ' + d.lat + ', ' + d.lng;
        initMap();
      } catch (e) { this.geoStatus = 'Erro de comunicação.'; }
    },
    submitForm(form) {
      form.submit();
    }
  }));
});

// Google Maps — exibe todas as locations com círculo de raio
function initMap() {
  const mapEl = document.getElementById('locations-map');
  if (!mapEl || typeof google === 'undefined') return;

  const locations = @json($locations->where('active', true)->values());
  if (!locations.length) return;

  const map = new google.maps.Map(mapEl, {
    zoom: 14,
    center: { lat: parseFloat(locations[0].latitude), lng: parseFloat(locations[0].longitude) },
    mapTypeId: 'roadmap',
    disableDefaultUI: false,
  });

  const bounds = new google.maps.LatLngBounds();

  locations.forEach(loc => {
    const center = { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) };

    new google.maps.Marker({
      position: center,
      map,
      title: loc.name,
      label: { text: loc.name[0], color: '#fff' },
    });

    new google.maps.Circle({
      map,
      center,
      radius: loc.radius_meters,
      strokeColor: '#10b981',
      strokeOpacity: 0.9,
      strokeWeight: 2,
      fillColor: '#10b981',
      fillOpacity: 0.12,
    });

    bounds.extend(new google.maps.LatLng(center.lat, center.lng));
  });

  if (locations.length > 1) map.fitBounds(bounds);
}
</script>

@if($googleMapsKey)
<script async defer
  src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=initMap">
</script>
@endif

@endsection
