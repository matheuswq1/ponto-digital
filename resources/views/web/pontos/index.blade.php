@extends('web.layout')
@section('title', 'Registros de ponto')
@section('page-title', 'Registros de ponto')

@section('content')

@php
    $typeLabels = [
        'entrada'           => 'Entrada',
        'saida'             => 'Saída',
        'intervalo_inicio'  => 'Intervalo Início',
        'intervalo_fim'     => 'Intervalo Fim',
    ];
    $typeColors = [
        'entrada'           => 'bg-emerald-100 text-emerald-700',
        'saida'             => 'bg-rose-100 text-rose-700',
        'intervalo_inicio'  => 'bg-orange-100 text-orange-700',
        'intervalo_fim'     => 'bg-teal-100 text-teal-700',
    ];
@endphp

{{-- Filtros --}}
<form method="get" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">De</label>
            @include('web.components.date-input', ['name'=>'date_from','value'=>$dateFrom])
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Até</label>
            @include('web.components.date-input', ['name'=>'date_to','value'=>$dateTo])
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Colaborador</label>
            <select name="employee_id" class="text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white min-w-[180px]">
                <option value="">Todos os colaboradores</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected($employeeId == $emp->id)>{{ $emp->user->name ?? $emp->id }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[150px]">
            <label class="block text-xs font-medium text-slate-500 mb-1">Buscar</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" name="q" value="{{ $search }}" placeholder="Nome do colaborador..."
                       class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
            <a href="{{ route('painel.pontos.index') }}" class="text-sm text-slate-500 hover:underline px-2 py-2">Limpar</a>
        </div>
        <div class="ml-auto flex flex-col items-end gap-1">
            <div class="flex items-center gap-2">
            <a href="{{ route('painel.pontos.cartao', ['date_from'=>$dateFrom,'date_to'=>$dateTo,'employee_id'=>$employeeId,'q'=>$search]) }}"
               target="_blank"
               title="Cartão do colaborador escolhido, da busca (se só um resultado) ou de todos"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-700 border border-indigo-300 bg-indigo-50 px-3 py-2 rounded-lg hover:bg-indigo-100 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Cartão Ponto
            </a>
            <a href="{{ route('painel.pontos.export', ['date_from'=>$dateFrom,'date_to'=>$dateTo,'employee_id'=>$employeeId,'q'=>$search]) }}"
               title="CSV conforme período, colaborador e busca atuais"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 border border-slate-300 bg-white px-3 py-2 rounded-lg hover:bg-slate-50 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Exportar CSV
            </a>
            </div>
            <p class="text-[11px] text-slate-400 max-w-xs text-right hidden sm:block">Individual: escolha o colaborador no filtro ou use os atalhos na tabela (primeiro registo de cada pessoa).</p>
        </div>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($records->isEmpty())
        <div class="p-12 text-center">
            <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <p class="text-sm text-slate-400">Nenhum registro no período selecionado.</p>
        </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Colaborador</th>
                    <th class="px-5 py-3">Tipo</th>
                    <th class="px-5 py-3">Data / Hora</th>
                    <th class="px-5 py-3 hidden md:table-cell">Localização</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Foto</th>
                    <th class="px-5 py-3 hidden sm:table-cell">Origem</th>
                    @can('delete-time-records')
                    <th class="px-5 py-3 text-right w-24">Ações</th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @php $exportShortcutSeen = []; @endphp
                @foreach($records as $rec)
                @php
                    $name    = $rec->employee->user->name ?? '—';
                    $initial = strtoupper(substr($name, 0, 1));
                    $colorCls = $typeColors[$rec->type] ?? 'bg-slate-100 text-slate-600';
                    $label    = $typeLabels[$rec->type] ?? $rec->type;
                    $dt = $rec->datetime_local;
                    $eid = $rec->employee_id;
                    $showExportShortcut = !isset($exportShortcutSeen[$eid]);
                    if ($showExportShortcut) {
                        $exportShortcutSeen[$eid] = true;
                    }
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $initial }}</div>
                            <div>
                                <span class="font-medium text-slate-800">{{ $name }}</span>
                                <p class="text-xs text-slate-400">{{ $rec->employee->cargo ?? '' }}</p>
                                @if($showExportShortcut)
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                        <a href="{{ route('painel.pontos.export', ['date_from'=>$dateFrom,'date_to'=>$dateTo,'employee_id'=>$eid]) }}"
                                           class="text-indigo-600 hover:underline font-medium">CSV só deste</a>
                                        <span class="text-slate-300">·</span>
                                        <a href="{{ route('painel.pontos.cartao', ['date_from'=>$dateFrom,'date_to'=>$dateTo,'employee_id'=>$eid]) }}"
                                           target="_blank" class="text-indigo-600 hover:underline font-medium">Cartão só deste</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorCls }}">{{ $label }}</span>
                    </td>
                    <td class="px-5 py-3 font-mono font-bold text-slate-800">
                        {{ $dt->format('H:i') }}
                        <span class="font-normal text-xs text-slate-400 ml-1">{{ $dt->format('d/m/Y') }}</span>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell text-xs text-slate-500">
                        @if($rec->latitude && $rec->longitude)
                            <a href="https://maps.google.com/?q={{ $rec->latitude }},{{ $rec->longitude }}" target="_blank"
                               class="text-indigo-500 hover:underline">
                                {{ number_format($rec->latitude,4) }}, {{ number_format($rec->longitude,4) }}
                            </a>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden lg:table-cell">
                        @if($rec->photo_url)
                            <a href="{{ $rec->photo_url }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-500 hover:underline">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                                Ver foto
                            </a>
                        @else
                            <span class="text-slate-300 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden sm:table-cell">
                        @if($rec->offline)
                            <span class="inline-flex items-center gap-1 text-xs text-amber-600 bg-amber-50 rounded-full px-2 py-0.5">Offline</span>
                        @else
                            <span class="text-xs text-slate-400">Online</span>
                        @endif
                    </td>
                    @can('delete-time-records')
                    <td class="px-5 py-3 text-right align-middle">
                        <button type="button"
                                class="text-xs font-medium text-rose-600 hover:text-rose-800 hover:underline"
                                data-destroy-url="{{ route('painel.pontos.destroy', $rec) }}"
                                data-label="#{{ $rec->id }} — {{ $name }} — {{ $label }} · {{ $dt->format('d/m/Y H:i') }}"
                                onclick="openDeletePontoModal(this)">
                            Excluir
                        </button>
                    </td>
                    @endcan
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

@include('web.components.ponto-delete-modal')

@endsection
