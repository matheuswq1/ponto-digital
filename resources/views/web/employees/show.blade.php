@extends('web.layout')
@section('title', $employee->user->name ?? 'Colaborador')
@section('page-title', 'Detalhes do Colaborador')

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Header do perfil --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="flex flex-wrap items-start gap-5">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700 text-2xl font-bold">
            {{ strtoupper(substr($employee->user->name ?? '?', 0, 1)) }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-3 mb-1">
                <h1 class="text-lg font-bold text-slate-800">{{ $employee->user->name ?? '—' }}</h1>
                @if($employee->active)
                    <span class="inline-flex items-center text-xs font-medium text-emerald-700 bg-emerald-100 rounded-full px-2.5 py-0.5">Ativo</span>
                @else
                    <span class="inline-flex items-center text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2.5 py-0.5">Inativo</span>
                @endif
                @if($employee->face_enrolled)
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 bg-indigo-100 rounded-full px-2.5 py-0.5">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        Facial cadastrado
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500">{{ $employee->cargo }}{{ ($employee->dept?->name ?? $employee->department) ? ' · ' . ($employee->dept?->name ?? $employee->department) : '' }}</p>
            <p class="text-sm text-slate-400 mt-0.5">{{ $employee->user->email ?? '' }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('painel.employees.edit', $employee) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 border border-slate-300 bg-white px-3 py-2 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                Editar
            </a>
        </div>
    </div>

    {{-- Detalhes em grade --}}
    <div class="mt-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 pt-5 border-t border-slate-100">
        <div>
            <p class="text-xs text-slate-400 mb-0.5">Empresa</p>
            <p class="text-sm font-medium text-slate-700">{{ $employee->company->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-0.5">Contrato</p>
            <p class="text-sm font-medium text-slate-700 uppercase">{{ $employee->contract_type }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-0.5">Jornada</p>
            <p class="text-sm font-medium text-slate-700">{{ $employee->weekly_hours }}h/sem</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-0.5">Admissão</p>
            <p class="text-sm font-medium text-slate-700">{{ $employee->admission_date?->format('d/m/Y') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400 mb-0.5">Total de registros</p>
            <p class="text-sm font-medium text-slate-700">{{ $totalRecords }}</p>
        </div>
    </div>
</div>

{{-- Histórico de pontos --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700">Histórico de pontos</h2>
        <form method="get" class="flex items-center gap-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}"
                   class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            <span class="text-slate-400 text-xs">até</span>
            <input type="date" name="date_to" value="{{ $dateTo }}"
                   class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            <button type="submit" class="text-sm font-medium text-white bg-indigo-600 px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
            <a href="{{ route('painel.pontos.cartao', ['employee_id' => $employee->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               target="_blank"
               class="text-sm font-medium text-indigo-700 border border-indigo-300 bg-indigo-50 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Cartão Ponto
            </a>
            <a href="{{ route('painel.pontos.export', ['employee_id' => $employee->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="text-sm font-medium text-slate-600 border border-slate-300 bg-white px-3 py-1.5 rounded-lg hover:bg-slate-50 transition inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                CSV
            </a>
        </form>
    </div>

    @if($records->isEmpty())
        <div class="p-10 text-center text-slate-400 text-sm">Nenhum registro no período selecionado.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Data / Hora</th>
                    <th class="px-5 py-3">Tipo</th>
                    <th class="px-5 py-3 hidden md:table-cell">Origem</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Localização</th>
                    <th class="px-5 py-3 hidden xl:table-cell">Foto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($records as $rec)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-slate-700">{{ $rec->datetime_local?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-5 py-3">
                        @php
                            $typeColors = ['entrada'=>'emerald','saida'=>'rose','intervalo_inicio'=>'amber','intervalo_fim'=>'sky'];
                            $color = $typeColors[$rec->type] ?? 'slate';
                        @endphp
                        <span class="inline-flex items-center text-xs font-medium text-{{ $color }}-700 bg-{{ $color }}-100 rounded-full px-2.5 py-0.5">
                            {{ ucfirst(str_replace('_', ' ', $rec->type)) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell text-slate-500 text-xs">{{ $rec->offline ? 'Offline' : 'Online' }}</td>
                    <td class="px-5 py-3 hidden lg:table-cell text-slate-500 text-xs">
                        @if($rec->latitude && $rec->longitude)
                            {{ number_format($rec->latitude, 4) }}, {{ number_format($rec->longitude, 4) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden xl:table-cell">
                        @if($rec->photo_url)
                            <a href="{{ $rec->photo_url }}" target="_blank" class="text-indigo-600 hover:underline text-xs">Ver foto</a>
                        @else
                            <span class="text-slate-400 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100 text-xs text-slate-500">
        <span>{{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }} registros</span>
        @if($records->hasPages())
        <nav class="inline-flex -space-x-px rounded overflow-hidden border border-slate-200 text-xs">
            @if($records->onFirstPage())
                <span class="px-3 py-1.5 bg-white text-slate-300 border-r border-slate-200">‹</span>
            @else
                <a href="{{ $records->previousPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">‹</a>
            @endif
            @foreach($records->getUrlRange(max(1,$records->currentPage()-2), min($records->lastPage(),$records->currentPage()+2)) as $page => $url)
                @if($page == $records->currentPage())
                    <span class="px-3 py-1.5 bg-indigo-600 text-white border-r border-indigo-600 font-semibold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">{{ $page }}</a>
                @endif
            @endforeach
            @if($records->hasMorePages())
                <a href="{{ $records->nextPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-600">›</a>
            @else
                <span class="px-3 py-1.5 bg-white text-slate-300">›</span>
            @endif
        </nav>
        @endif
    </div>
    @endif
</div>

{{-- Banco de Horas --}}
@php
    $balanceMinutes   = $employee->hour_bank_balance_minutes;
    $balanceFormatted = $employee->hour_bank_balance_formatted;
    $recentTx         = $employee->hourBankTransactions()->orderByDesc('reference_date')->limit(5)->get();
@endphp
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <h2 class="text-sm font-semibold text-slate-700">Banco de Horas</h2>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-2xl font-bold {{ $balanceMinutes >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $balanceFormatted }}</span>
            <a href="{{ route('painel.hour-bank.employee', $employee) }}"
               class="text-xs text-indigo-600 hover:underline font-medium">Ver completo →</a>
        </div>
    </div>

    @if($recentTx->isEmpty())
    <div class="px-5 py-6 text-sm text-slate-400 text-center">Nenhuma movimentação registrada.</div>
    @else
    <div class="divide-y divide-slate-100">
        @foreach($recentTx as $tx)
        @php
            $isCredit = $tx->minutes > 0;
            $abs      = abs($tx->minutes);
            $sign     = $isCredit ? '+' : '-';
            $fmt      = sprintf('%s%02d:%02d', $sign, intdiv($abs,60), $abs%60);
        @endphp
        <div class="flex items-center gap-3 px-5 py-2.5">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-slate-600">{{ $tx->description ?? $tx->getTypeLabel() }}</p>
                <p class="text-xs text-slate-400">{{ $tx->reference_date->format('d/m/Y') }}</p>
            </div>
            <p class="text-sm font-bold {{ $isCredit ? 'text-emerald-600' : 'text-rose-600' }}">{{ $fmt }}</p>
        </div>
        @endforeach
    </div>
    @endif
</div>

@endsection
