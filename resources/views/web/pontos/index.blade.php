@extends('web.layout')
@section('title', 'Registros de ponto')
@section('page-title', 'Registros de ponto')

@section('content')

@php
    $typeLabels = ['entrada'=>'Entrada','saida_almoco'=>'Saída Almoço','volta_almoco'=>'Volta Almoço','saida'=>'Saída'];
    $typeColors = [
        'entrada'      => 'bg-emerald-100 text-emerald-700',
        'saida_almoco' => 'bg-amber-100 text-amber-700',
        'volta_almoco' => 'bg-sky-100 text-sky-700',
        'saida'        => 'bg-rose-100 text-rose-700',
    ];
@endphp

{{-- Filtros --}}
<form method="get" class="flex flex-wrap items-center gap-3 mb-6">
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/>
        </svg>
        <input type="date" name="date" value="{{ $date }}"
               class="pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
    </div>
    <div class="relative flex-1 max-w-xs">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input type="text" name="q" value="{{ $search }}" placeholder="Buscar colaborador..."
               class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
    </div>
    <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Aplicar</button>

    {{-- Nav. dias --}}
    <div class="flex items-center gap-1 ml-auto">
        <a href="?date={{ \Carbon\Carbon::parse($date)->subDay()->toDateString() }}&q={{ $search }}"
           class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 transition" title="Dia anterior">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        </a>
        <span class="text-sm font-medium text-slate-700 px-2">
            {{ $dateObj->locale('pt_BR')->isoFormat('ddd, D [de] MMM') }}
        </span>
        <a href="?date={{ \Carbon\Carbon::parse($date)->addDay()->toDateString() }}&q={{ $search }}"
           class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 transition" title="Próximo dia">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </a>
        <a href="?date={{ today()->toDateString() }}&q={{ $search }}"
           class="ml-1 text-xs text-indigo-500 hover:underline">Hoje</a>
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($records->isEmpty())
        <div class="p-12 text-center">
            <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <p class="text-sm text-slate-400">Nenhum registro em {{ $dateObj->format('d/m/Y') }}{{ $search ? ' para "' . e($search) . '"' : '' }}.</p>
        </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Colaborador</th>
                    <th class="px-5 py-3">Tipo</th>
                    <th class="px-5 py-3">Horário</th>
                    <th class="px-5 py-3 hidden md:table-cell">Localização</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Foto</th>
                    <th class="px-5 py-3 hidden sm:table-cell">Origem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($records as $rec)
                @php
                    $name = $rec->employee->user->name ?? '—';
                    $initial = strtoupper(substr($name, 0, 1));
                    $colorCls = $typeColors[$rec->type] ?? 'bg-slate-100 text-slate-600';
                    $label    = $typeLabels[$rec->type] ?? $rec->type;
                    $dt = \Carbon\Carbon::parse($rec->datetime);
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $initial }}</div>
                            <span class="font-medium text-slate-800">{{ $name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorCls }}">{{ $label }}</span>
                    </td>
                    <td class="px-5 py-3 font-mono font-bold text-slate-800">{{ $dt->format('H:i') }}
                        <span class="font-normal text-xs text-slate-400 ml-1">{{ $dt->format('d/m') }}</span>
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
                            <a href="{{ $rec->photo_url }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs text-indigo-500 hover:underline">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                                Ver foto
                            </a>
                        @else
                            <span class="text-slate-300 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden sm:table-cell">
                        @if($rec->offline)
                            <span class="inline-flex items-center gap-1 text-xs text-amber-600 bg-amber-50 rounded-full px-2 py-0.5">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                Offline
                            </span>
                        @else
                            <span class="text-xs text-slate-400">Online</span>
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

@endsection
