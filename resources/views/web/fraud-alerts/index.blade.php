@extends('web.layout')
@section('title', 'Alertas de Fraude')
@section('page-title', 'Alertas de Fraude')

@section('content')

@php
$ruleBadge = [
    'mock_location'    => ['label' => 'GPS Falso',            'cls' => 'bg-rose-100 text-rose-700'],
    'velocity_jump'    => ['label' => 'Salto de Localização', 'cls' => 'bg-orange-100 text-orange-700'],
    'wifi_mismatch'    => ['label' => 'Wi-Fi n/ autorizado',  'cls' => 'bg-amber-100 text-amber-700'],
    'ip_city_mismatch' => ['label' => 'Cidade IP divergente', 'cls' => 'bg-purple-100 text-purple-700'],
];
$actionBadge = [
    'blocked' => 'bg-rose-100 text-rose-700',
    'warned'  => 'bg-amber-100 text-amber-700',
    'logged'  => 'bg-slate-100 text-slate-600',
];
@endphp

{{-- Filtros --}}
<form method="get" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">De</label>
            @include('web.components.date-input', ['name' => 'date_from', 'value' => $dateFrom])
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Até</label>
            @include('web.components.date-input', ['name' => 'date_to', 'value' => $dateTo])
        </div>

        @if($companies->isNotEmpty())
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Empresa</label>
            <select name="company_id" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white min-w-[160px]">
                <option value="">Todas</option>
                @foreach($companies as $co)
                    <option value="{{ $co->id }}" @selected($companyId == $co->id)>{{ $co->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Colaborador</label>
            <select name="employee_id" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white min-w-[160px]">
                <option value="">Todos</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected($employeeId == $emp->id)>{{ $emp->user->name ?? $emp->id }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Regra</label>
            <select name="rule" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                <option value="">Todas</option>
                @foreach($rulesOptions as $val => $lbl)
                    <option value="{{ $val }}" @selected($rule === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Acção</label>
            <select name="action" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                <option value="">Todas</option>
                <option value="blocked" @selected($action === 'blocked')>Bloqueado</option>
                <option value="warned"  @selected($action === 'warned')>Avisado</option>
                <option value="logged"  @selected($action === 'logged')>Registado</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
            <a href="{{ route('painel.fraud-alerts.index') }}" class="text-sm text-slate-500 hover:underline px-2 py-2">Limpar</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($attempts->isEmpty())
        <div class="p-12 text-center">
            <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
            </svg>
            <p class="text-sm text-slate-400">Nenhuma tentativa de fraude no período.</p>
        </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Colaborador</th>
                    <th class="px-5 py-3">Regra</th>
                    <th class="px-5 py-3">Acção</th>
                    <th class="px-5 py-3 hidden md:table-cell">IP</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Coords</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Device</th>
                    <th class="px-5 py-3">Data / Hora</th>
                    <th class="px-5 py-3 hidden xl:table-cell w-64">Detalhes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($attempts as $att)
                @php
                    $name    = $att->employee->user->name ?? '—';
                    $initial = strtoupper(substr($name, 0, 1));
                    $rb      = $ruleBadge[$att->rule_triggered] ?? ['label' => $att->rule_triggered, 'cls' => 'bg-slate-100 text-slate-600'];
                    $ab      = $actionBadge[$att->action_taken] ?? 'bg-slate-100 text-slate-600';
                    $dt      = $att->created_at->setTimezone(config('app.timezone', 'America/Sao_Paulo'));
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700 text-xs font-bold">{{ $initial }}</div>
                            <div>
                                <span class="font-medium text-slate-800">{{ $name }}</span>
                                @if($companies->isNotEmpty())
                                <p class="text-xs text-slate-400">{{ $att->company->name ?? '—' }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $rb['cls'] }}">{{ $rb['label'] }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ab }}">
                            {{ $att->getActionLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell text-xs text-slate-500 font-mono">{{ $att->ip_address ?? '—' }}</td>
                    <td class="px-5 py-3 hidden lg:table-cell text-xs text-slate-500">
                        @if($att->latitude && $att->longitude)
                            <a href="https://maps.google.com/?q={{ $att->latitude }},{{ $att->longitude }}" target="_blank" class="text-indigo-500 hover:underline">
                                {{ number_format($att->latitude, 4) }}, {{ number_format($att->longitude, 4) }}
                            </a>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden lg:table-cell text-xs text-slate-400 font-mono truncate max-w-[120px]">{{ $att->device_id ?? '—' }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">
                        {{ $dt->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="px-5 py-3 hidden xl:table-cell text-xs text-slate-500">
                        @if($att->details)
                            @foreach($att->details as $k => $v)
                                <span class="inline-block mr-1 mb-0.5 bg-slate-100 rounded px-1.5 py-0.5 font-mono text-[10px]">{{ $k }}: {{ is_array($v) ? implode(', ', $v) : $v }}</span>
                            @endforeach
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100 text-xs text-slate-500">
        <span>{{ $attempts->firstItem() }}–{{ $attempts->lastItem() }} de {{ $attempts->total() }} registos</span>
        @if($attempts->hasPages())
        <nav class="inline-flex -space-x-px rounded overflow-hidden border border-slate-200 text-xs">
            @if($attempts->onFirstPage())
                <span class="px-3 py-1.5 bg-white text-slate-300 border-r border-slate-200">‹</span>
            @else
                <a href="{{ $attempts->previousPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">‹</a>
            @endif
            @foreach($attempts->getUrlRange(max(1,$attempts->currentPage()-2), min($attempts->lastPage(),$attempts->currentPage()+2)) as $page => $url)
                @if($page == $attempts->currentPage())
                    <span class="px-3 py-1.5 bg-indigo-600 text-white border-r border-indigo-600 font-semibold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">{{ $page }}</a>
                @endif
            @endforeach
            @if($attempts->hasMorePages())
                <a href="{{ $attempts->nextPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-600">›</a>
            @else
                <span class="px-3 py-1.5 bg-white text-slate-300">›</span>
            @endif
        </nav>
        @endif
    </div>
    @endif
</div>

@endsection
