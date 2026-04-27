@extends('web.layout')
@section('title', 'Início')
@section('page-title', 'Dashboard')

@section('content')

@php
    $periodLabel = $rangeStart->locale('pt_BR')->isoFormat('D MMM') . ' — ' . $rangeEnd->locale('pt_BR')->isoFormat('D MMM YYYY');
    $qCompany = (auth()->user()->isAdmin() && ($companyId ?? null)) ? ['company_id' => $companyId] : [];
@endphp

@if(auth()->user()->isAdmin() || auth()->user()->isGestor())
<form method="get" class="mb-6 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Período</label>
        <select name="period" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white min-w-[11rem]">
            <option value="today" @selected($period==='today')>Hoje</option>
            <option value="7d" @selected($period==='7d')>Últimos 7 dias</option>
            <option value="30d" @selected($period==='30d')>Últimos 30 dias</option>
            <option value="month" @selected($period==='month')>Mês corrente</option>
            <option value="custom" @selected($period==='custom')>Personalizado</option>
        </select>
    </div>
    @if($period === 'custom')
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">De</label>
        <input type="date" name="date_from" value="{{ $dateFromParam }}" class="text-sm border border-slate-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Até</label>
        <input type="date" name="date_to" value="{{ $dateToParam }}" class="text-sm border border-slate-300 rounded-lg px-3 py-2">
    </div>
    @endif
    @if(auth()->user()->isAdmin() && $companies->isNotEmpty())
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Empresa</label>
        <select name="company_id" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white min-w-[12rem]">
            <option value="">Todas</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg">Aplicar</button>
</form>
@endif

{{-- ===== CARDS ===== --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">

    @if(auth()->user()->isAdmin() || auth()->user()->isGestor())
    {{-- Correções pendentes --}}
    <a href="{{ route('painel.edit-requests.index') }}"
       class="group relative bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md hover:border-indigo-300 transition">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Correções pendentes</p>
                <p class="mt-2 text-4xl font-bold text-slate-800">{{ $pendingEdits }}</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $pendingEdits > 0 ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-400' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                </svg>
            </span>
        </div>
        <p class="mt-3 text-xs text-indigo-500 group-hover:underline">Ver solicitações →</p>
    </a>

    {{-- Colaboradores ativos --}}
    <a href="{{ route('painel.employees.index') }}"
       class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md hover:border-indigo-300 transition">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Colaboradores ativos</p>
                <p class="mt-2 text-4xl font-bold text-slate-800">{{ $employeesCount }}</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
            </span>
        </div>
        <p class="mt-3 text-xs text-indigo-500 group-hover:underline">Ver colaboradores →</p>
    </a>

    {{-- Registros hoje --}}
    <a href="{{ route('painel.pontos.index', $qCompany) }}"
       class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md hover:border-indigo-300 transition">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Registos no período</p>
                <p class="mt-2 text-4xl font-bold text-slate-800">{{ $recordsInRange }}</p>
                <p class="mt-1 text-xs text-slate-400">Colaboradores com registo: {{ $uniqueEmployees }}</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </span>
        </div>
        <p class="mt-2 text-xs text-slate-500 line-clamp-2">{{ $periodLabel }}</p>
        <p class="mt-2 text-xs text-indigo-500 group-hover:underline">Ver pontos →</p>
    </a>
    @endif

    {{-- Meus pontos hoje (funcionário) --}}
    @if(auth()->user()->employee)
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Meus pontos hoje</p>
                <p class="mt-2 text-4xl font-bold text-slate-800">{{ $todayCount }}</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </span>
        </div>
        <p class="mt-3 text-xs text-slate-400">Use o app móvel para registrar</p>
    </div>
    @endif
</div>

@if(auth()->user()->isAdmin() || auth()->user()->isGestor())

{{-- Cards extras: ausentes + banco de horas pendentes --}}
<div class="grid gap-4 sm:grid-cols-2 mb-6">
    <div class="bg-white rounded-xl border {{ $absentsEndDay > 0 ? 'border-rose-200' : 'border-slate-200' }} p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Sem ponto (último dia)</p>
        <p class="mt-2 text-4xl font-bold {{ $absentsEndDay > 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $absentsEndDay }}</p>
        <p class="mt-1 text-xs text-slate-400">Colaboradores sem registo a {{ $rangeEnd->format('d/m/Y') }} (empresa do filtro)</p>
    </div>
    <a href="{{ route('painel.hour-bank.index') }}"
       class="group bg-white rounded-xl border {{ $pendingHourBank > 0 ? 'border-amber-200' : 'border-slate-200' }} p-5 shadow-sm hover:shadow-md transition">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Banco de horas pendentes</p>
        <p class="mt-2 text-4xl font-bold {{ $pendingHourBank > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $pendingHourBank }}</p>
        <p class="mt-3 text-xs text-indigo-500 group-hover:underline">Ver solicitações →</p>
    </a>
</div>

<div class="grid gap-6 lg:grid-cols-5 mb-6">

    {{-- ===== GRÁFICO DE BARRAS (7 dias) ===== --}}
    <div class="lg:col-span-3 bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700 mb-1">Pontos registados</h2>
        <p class="text-xs text-slate-400 mb-3">{{ $periodLabel }}</p>
        <div class="flex items-end gap-2 h-36">
            @foreach($weekChart as $day)
            @php $pct = $chartMax > 0 ? ($day['count'] / $chartMax) * 100 : 0; @endphp
            <div class="flex-1 flex flex-col items-center gap-1">
                <span class="text-[10px] font-semibold text-slate-600">{{ $day['count'] > 0 ? $day['count'] : '' }}</span>
                <div class="w-full flex flex-col justify-end" style="height:100px">
                    <div class="w-full rounded-t-md {{ $loop->last ? 'bg-indigo-500' : 'bg-indigo-200' }} transition-all"
                         style="height:{{ max(4, $pct) }}%"></div>
                </div>
                <span class="text-[10px] text-slate-400 text-center leading-tight">{{ $day['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ===== ATIVIDADE RECENTE ===== --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Registos recentes</h2>
            <a href="{{ route('painel.pontos.index', $qCompany) }}" class="text-xs text-indigo-500 hover:underline">Ver todos</a>
        </div>
        @if($recentRecords->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-400 text-center">Nenhum registo no período.</p>
        @else
        <ul class="divide-y divide-slate-100">
            @foreach($recentRecords as $rec)
            @php
                $colors = [
                    'entrada' => ['dot'=>'bg-emerald-500','badge'=>'bg-emerald-100 text-emerald-700'],
                    'saida'   => ['dot'=>'bg-rose-500',   'badge'=>'bg-rose-100 text-rose-700'],
                ];
                $c = $colors[$rec->type] ?? ['dot'=>'bg-slate-400','badge'=>'bg-slate-100 text-slate-600'];
                $labels = ['entrada'=>'Entrada','saida'=>'Saída'];
            @endphp
            <li class="flex items-center gap-3 px-5 py-2.5">
                <span class="h-2 w-2 rounded-full {{ $c['dot'] }} shrink-0"></span>
                <p class="flex-1 text-sm text-slate-700 truncate">{{ $rec->employee->user->name ?? '—' }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $c['badge'] }}">{{ $labels[$rec->type] ?? $rec->type }}</span>
                <span class="text-xs text-slate-400 shrink-0">{{ $rec->datetime_local?->format('H:i') }}</span>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>

{{-- ===== TABELA POR DEPARTAMENTO ===== --}}
@if($deptStats->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Por departamento (período)</h2>
        <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase">
                <tr>
                    @if(auth()->user()->isAdmin())<th class="px-4 py-2 text-left">Empresa</th>@endif
                    <th class="px-4 py-2 text-left">Departamento</th>
                    <th class="px-4 py-2 text-center">Total</th>
                    <th class="px-4 py-2 text-center">Com ponto</th>
                    <th class="px-4 py-2 text-center">Ausentes</th>
                    <th class="px-4 py-2 text-center">Presença</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($deptStats as $ds)
                @php $pct = $ds['total'] > 0 ? round($ds['ponto'] / $ds['total'] * 100) : 0; @endphp
                <tr class="hover:bg-slate-50">
                    @if(auth()->user()->isAdmin())<td class="px-4 py-2 text-slate-500 text-xs">{{ $ds['company'] }}</td>@endif
                    <td class="px-4 py-2 font-medium text-slate-800">{{ $ds['name'] }}</td>
                    <td class="px-4 py-2 text-center text-slate-600">{{ $ds['total'] }}</td>
                    <td class="px-4 py-2 text-center text-emerald-600 font-semibold">{{ $ds['ponto'] }}</td>
                    <td class="px-4 py-2 text-center {{ $ds['ausentes'] > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">{{ $ds['ausentes'] }}</td>
                    <td class="px-4 py-2 text-center">
                        <div class="flex items-center gap-2 justify-center">
                            <div class="w-20 bg-slate-100 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full {{ $pct >= 80 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-400' : 'bg-rose-400') }}"
                                     style="width:{{ $pct }}%"></div>
                            </div>
                            <span class="text-xs text-slate-600">{{ $pct }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@else
{{-- Funcionário: mensagem simples --}}
<div class="bg-white rounded-xl border border-slate-200 p-8 text-center shadow-sm">
    <svg class="mx-auto w-12 h-12 text-indigo-300 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-6-3h.008v.008H6v-.008Zm0 3h.008v.008H6v-.008Z"/>
    </svg>
    <h2 class="text-lg font-semibold text-slate-700 mb-1">Registo de ponto no app móvel</h2>
    <p class="text-sm text-slate-500">O dia a dia do ponto é feito pelo app. Este painel mostra apenas informações de gestão.</p>
</div>
@endif

@endsection
