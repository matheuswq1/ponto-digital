@extends('web.layout')

@section('title', 'Relatório de Presença')

@section('content')
@php
$dfCarbon = \Carbon\Carbon::parse($dateFrom);
$dtCarbon = \Carbon\Carbon::parse($dateTo);
$totalDias = count($dates);

$legendaStatus = [
    'P'  => ['label' => 'Presente',  'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
    'F'  => ['label' => 'Falta',     'bg' => 'bg-rose-100',    'text' => 'text-rose-700',    'dot' => 'bg-rose-500'],
    'H'  => ['label' => 'Feriado',   'bg' => 'bg-violet-100',  'text' => 'text-violet-700',  'dot' => 'bg-violet-400'],
    'Fo' => ['label' => 'Folga',     'bg' => 'bg-slate-100',   'text' => 'text-slate-400',   'dot' => 'bg-slate-300'],
];

$totalP  = array_sum(array_column($rows, 'total_p'));
$totalF  = array_sum(array_column($rows, 'total_f'));
$totalH  = array_sum(array_column($rows, 'total_h'));
$totalFo = array_sum(array_column($rows, 'total_fo'));

$diasSemana = ['D','S','T','Q','Q','S','S'];
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Relatório de Presença</h1>
        <p class="text-sm text-slate-500 mt-0.5">Mapa de presença e ausência por colaborador no período</p>
    </div>
</div>

{{-- Filtros --}}
<form method="get" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">De</label>
            @include('web.components.date-input', ['name' => 'date_from', 'value' => $dateFrom])
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Até</label>
            @include('web.components.date-input', ['name' => 'date_to', 'value' => $dateTo])
        </div>
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Empresa</label>
            <select name="company_id" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                <option value="">Todas</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Departamento</label>
            <select name="dept_id" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                <option value="">Todos</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected($deptId == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="flex items-center gap-3 mt-4 flex-wrap">
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
            Gerar relatório
        </button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
           class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition">
            ⬇ Exportar CSV
        </a>
        @if(count($rows) > 0)
        <span class="text-xs text-slate-400">{{ count($rows) }} colaborador(es) &middot;
            {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}</span>
        @endif
    </div>
</form>

@if(count($rows) > 0)

{{-- Totalizadores --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center">
        <p class="text-xs font-semibold text-emerald-600 mb-1">Total Presenças</p>
        <p class="text-2xl font-bold text-emerald-700">{{ $totalP }}</p>
    </div>
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-center">
        <p class="text-xs font-semibold text-rose-600 mb-1">Total Faltas</p>
        <p class="text-2xl font-bold text-rose-700">{{ $totalF }}</p>
    </div>
    <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-center">
        <p class="text-xs font-semibold text-violet-600 mb-1">Feriados</p>
        <p class="text-2xl font-bold text-violet-700">{{ $totalH }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center">
        <p class="text-xs font-semibold text-slate-500 mb-1">Folgas</p>
        <p class="text-2xl font-bold text-slate-600">{{ $totalFo }}</p>
    </div>
</div>

{{-- Legenda --}}
<div class="flex items-center gap-4 mb-4 flex-wrap">
    @foreach($legendaStatus as $key => $leg)
    <div class="flex items-center gap-1.5 text-xs {{ $leg['text'] }}">
        <span class="inline-block w-3 h-3 rounded-sm {{ $leg['bg'] }} border border-current"></span>
        <span class="font-semibold">{{ $key }}</span> — {{ $leg['label'] }}
    </div>
    @endforeach
</div>

{{-- Mapa de presença --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="text-xs border-collapse w-full">
            <thead>
                {{-- Linha dos meses --}}
                <tr class="bg-slate-800 text-white">
                    <th class="px-3 py-2 text-left font-semibold min-w-[160px] sticky left-0 bg-slate-800 z-10">Colaborador</th>
                    @php
                        $prevMonth = null;
                        $monthSpans = [];
                        $spanCount  = 0;
                        $spanMonth  = null;
                        foreach ($dates as $d) {
                            $m = \Carbon\Carbon::parse($d)->format('M/Y');
                            if ($m !== $spanMonth) {
                                if ($spanMonth !== null) $monthSpans[] = ['label' => $spanMonth, 'span' => $spanCount];
                                $spanMonth = $m; $spanCount = 1;
                            } else { $spanCount++; }
                        }
                        if ($spanMonth) $monthSpans[] = ['label' => $spanMonth, 'span' => $spanCount];
                    @endphp
                    @foreach($monthSpans as $ms)
                    <th colspan="{{ $ms['span'] }}" class="px-1 py-2 text-center font-semibold border-l border-slate-700">{{ $ms['label'] }}</th>
                    @endforeach
                    <th class="px-2 py-2 text-center min-w-[28px]" title="Presenças">P</th>
                    <th class="px-2 py-2 text-center min-w-[28px] text-rose-300" title="Faltas">F</th>
                    <th class="px-2 py-2 text-center min-w-[28px] text-violet-300" title="Feriados">H</th>
                </tr>
                {{-- Linha dos dias --}}
                <tr class="bg-slate-700 text-slate-200">
                    <th class="px-3 py-1 text-left sticky left-0 bg-slate-700 z-10">Departamento</th>
                    @foreach($dates as $d)
                    @php
                        $dCarbon = \Carbon\Carbon::parse($d);
                        $dow = (int) $dCarbon->format('w');
                        $isWeekend = in_array($dow, [0, 6]);
                    @endphp
                    <th class="px-0.5 py-1 text-center w-7 {{ $isWeekend ? 'text-slate-400' : '' }}" title="{{ $dCarbon->format('d/m/Y') }}">
                        <span class="block">{{ $diasSemana[$dow] }}</span>
                        <span class="block font-bold">{{ $dCarbon->format('d') }}</span>
                    </th>
                    @endforeach
                    <th colspan="3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                @php
                    $bgRow = $i % 2 === 0 ? 'bg-white' : 'bg-slate-50';
                    $totalDiasUteis = $row['total_p'] + $row['total_f'];
                    $pct = $totalDiasUteis > 0 ? round($row['total_p'] / $totalDiasUteis * 100) : 0;
                @endphp
                <tr class="{{ $bgRow }} hover:bg-brand-50 transition-colors">
                    <td class="px-3 py-1.5 sticky left-0 {{ $bgRow }} z-10 border-r border-slate-200">
                        <p class="font-semibold text-slate-800 leading-tight">{{ $row['nome'] }}</p>
                        <p class="text-[10px] text-slate-400">{{ $row['depto'] }}</p>
                    </td>
                    @foreach($dates as $d)
                    @php
                        $st = $row['dias'][$d] ?? 'Fo';
                        $leg = $legendaStatus[$st] ?? $legendaStatus['Fo'];
                        $dow = (int) \Carbon\Carbon::parse($d)->format('w');
                        $isWeekend = in_array($dow, [0, 6]);
                    @endphp
                    <td class="p-0.5 text-center w-7">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded text-[9px] font-bold {{ $leg['bg'] }} {{ $leg['text'] }}
                            {{ $isWeekend && $st === 'Fo' ? 'opacity-50' : '' }}">
                            {{ $st }}
                        </span>
                    </td>
                    @endforeach
                    <td class="px-2 py-1.5 text-center font-bold text-emerald-700">{{ $row['total_p'] }}</td>
                    <td class="px-2 py-1.5 text-center font-bold {{ $row['total_f'] > 0 ? 'text-rose-600' : 'text-slate-300' }}">{{ $row['total_f'] ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center text-violet-600">{{ $row['total_h'] ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@else
<div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
    <svg class="mx-auto mb-3 w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
    </svg>
    <p class="text-slate-400 text-sm">Nenhum colaborador encontrado para os filtros selecionados.</p>
</div>
@endif

@endsection
