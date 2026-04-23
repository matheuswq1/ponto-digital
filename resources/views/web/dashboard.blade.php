@extends('web.layout')
@section('title', 'Início')
@section('page-title', 'Dashboard')

@section('content')

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
    <a href="{{ route('painel.pontos.index') }}"
       class="group bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md hover:border-indigo-300 transition">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Registros hoje</p>
                <p class="mt-2 text-4xl font-bold text-slate-800">{{ $todayTotal }}</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </span>
        </div>
        <p class="mt-3 text-xs text-indigo-500 group-hover:underline">Ver pontos →</p>
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
<div class="grid gap-6 lg:grid-cols-5">

    {{-- ===== GRÁFICO DE BARRAS (7 dias) ===== --}}
    <div class="lg:col-span-3 bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Pontos registados — últimos 7 dias</h2>
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
            <h2 class="text-sm font-semibold text-slate-700">Últimos registros hoje</h2>
            <a href="{{ route('painel.pontos.index') }}" class="text-xs text-indigo-500 hover:underline">Ver todos</a>
        </div>
        @if($recentRecords->isEmpty())
            <p class="px-5 py-8 text-sm text-slate-400 text-center">Nenhum registro hoje.</p>
        @else
        <ul class="divide-y divide-slate-100">
            @foreach($recentRecords as $rec)
            @php
                $colors = [
                    'entrada'      => ['dot'=>'bg-emerald-500','badge'=>'bg-emerald-100 text-emerald-700'],
                    'saida_almoco' => ['dot'=>'bg-amber-500',  'badge'=>'bg-amber-100 text-amber-700'],
                    'volta_almoco' => ['dot'=>'bg-sky-500',    'badge'=>'bg-sky-100 text-sky-700'],
                    'saida'        => ['dot'=>'bg-rose-500',   'badge'=>'bg-rose-100 text-rose-700'],
                ];
                $c = $colors[$rec->type] ?? ['dot'=>'bg-slate-400','badge'=>'bg-slate-100 text-slate-600'];
                $labels = ['entrada'=>'Entrada','saida_almoco'=>'Saída Almoço','volta_almoco'=>'Volta Almoço','saida'=>'Saída'];
            @endphp
            <li class="flex items-center gap-3 px-5 py-2.5">
                <span class="h-2 w-2 rounded-full {{ $c['dot'] }} shrink-0"></span>
                <p class="flex-1 text-sm text-slate-700 truncate">{{ $rec->employee->user->name ?? '—' }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $c['badge'] }}">{{ $labels[$rec->type] ?? $rec->type }}</span>
                <span class="text-xs text-slate-400 shrink-0">{{ \Carbon\Carbon::parse($rec->datetime)->format('H:i') }}</span>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>

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
