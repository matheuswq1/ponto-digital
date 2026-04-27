@extends('web.layout')

@section('title', 'Folha de Pagamento')

@section('content')
@php
function rpt_fmt_min(int $m): string {
    if ($m === 0) return '00:00';
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

$totalTrabalhado  = array_sum(array_column($rows, 'trabalhado_min'));
$totalExtra       = array_sum(array_column($rows, 'extra_min'));
$totalExtra50     = array_sum(array_column($rows, 'extra_50_min'));
$totalExtra100    = array_sum(array_column($rows, 'extra_100_min'));
$totalExtraNoc    = array_sum(array_column($rows, 'extra_noc_min'));
$totalFalta       = array_sum(array_column($rows, 'falta_min'));
$totalDiasTrab    = array_sum(array_column($rows, 'dias_trabalhados'));
$totalDiasFalta   = array_sum(array_column($rows, 'dias_falta'));
$dfCarbon = \Carbon\Carbon::parse($dateFrom);
$dtCarbon = \Carbon\Carbon::parse($dateTo);
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Folha de Pagamento</h1>
        <p class="text-sm text-slate-500 mt-0.5">Relatório consolidado de horas por colaborador</p>
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
    <div class="flex items-center gap-3 mt-4">
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

{{-- Cards de totais --}}
@if(count($rows) > 0)
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    @php
    $cards_top = [
        ['label' => 'Colaboradores',  'value' => count($rows),               'color' => 'brand',   'fmt' => false],
        ['label' => 'Dias trabalhados','value' => $totalDiasTrab,             'color' => 'emerald', 'fmt' => false],
        ['label' => 'Dias de falta',  'value' => $totalDiasFalta,            'color' => 'rose',    'fmt' => false],
        ['label' => 'H. trabalhadas', 'value' => rpt_fmt_min($totalTrabalhado), 'color' => 'slate', 'fmt' => true],
        ['label' => 'HE 50%',         'value' => rpt_fmt_min($totalExtra50),  'color' => 'amber',  'fmt' => true],
        ['label' => 'HE 100%',        'value' => rpt_fmt_min($totalExtra100), 'color' => 'violet', 'fmt' => true],
        ['label' => 'Ad. Noturno',    'value' => rpt_fmt_min($totalExtraNoc), 'color' => 'sky',    'fmt' => true],
    ];
    $colorMap = [
        'brand'   => 'bg-brand-50 border-brand-200 text-brand-700',
        'emerald' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
        'rose'    => 'bg-rose-50 border-rose-200 text-rose-700',
        'slate'   => 'bg-slate-50 border-slate-200 text-slate-700',
        'amber'   => 'bg-amber-50 border-amber-200 text-amber-700',
        'violet'  => 'bg-violet-50 border-violet-200 text-violet-700',
        'sky'     => 'bg-sky-50 border-sky-200 text-sky-700',
    ];
    @endphp
    @foreach($cards_top as $ct)
    <div class="rounded-xl border p-3 text-center {{ $colorMap[$ct['color']] }}">
        <p class="text-xs font-semibold mb-1 opacity-70">{{ $ct['label'] }}</p>
        <p class="text-lg font-bold leading-tight">{{ $ct['value'] }}</p>
    </div>
    @endforeach
</div>

{{-- Tabela --}}
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-slate-800 text-slate-100 text-xs">
                    <th class="px-3 py-3 text-left font-semibold">Colaborador</th>
                    <th class="px-3 py-3 text-left font-semibold hidden md:table-cell">Matrícula</th>
                    <th class="px-3 py-3 text-left font-semibold hidden lg:table-cell">CPF</th>
                    <th class="px-3 py-3 text-left font-semibold hidden xl:table-cell">Cargo</th>
                    <th class="px-3 py-3 text-left font-semibold hidden xl:table-cell">Depto.</th>
                    <th class="px-2 py-3 text-center font-semibold">Dias Trab.</th>
                    <th class="px-2 py-3 text-center font-semibold text-rose-300">Faltas</th>
                    <th class="px-2 py-3 text-center font-semibold">H. Trab.</th>
                    <th class="px-2 py-3 text-center font-semibold text-amber-300">HE 50%</th>
                    <th class="px-2 py-3 text-center font-semibold text-violet-300">HE 100%</th>
                    <th class="px-2 py-3 text-center font-semibold text-sky-300">Ad. Not.</th>
                    <th class="px-2 py-3 text-center font-semibold text-rose-300">H. Falta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rows as $i => $row)
                <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-slate-50' }} hover:bg-brand-50 transition-colors">
                    <td class="px-3 py-2.5">
                        <p class="font-semibold text-slate-800 leading-tight">{{ $row['nome'] }}</p>
                        <p class="text-xs text-slate-400">{{ $row['empresa'] }}</p>
                    </td>
                    <td class="px-3 py-2.5 text-slate-500 text-xs hidden md:table-cell">{{ $row['matricula'] }}</td>
                    <td class="px-3 py-2.5 text-slate-500 text-xs hidden lg:table-cell">{{ $row['cpf'] }}</td>
                    <td class="px-3 py-2.5 text-slate-600 text-xs hidden xl:table-cell">{{ $row['cargo'] }}</td>
                    <td class="px-3 py-2.5 text-slate-600 text-xs hidden xl:table-cell">{{ $row['departamento'] }}</td>
                    <td class="px-2 py-2.5 text-center font-semibold text-slate-700">{{ $row['dias_trabalhados'] }}</td>
                    <td class="px-2 py-2.5 text-center font-semibold {{ $row['dias_falta'] > 0 ? 'text-rose-600' : 'text-slate-300' }}">
                        {{ $row['dias_falta'] > 0 ? $row['dias_falta'] : '—' }}
                    </td>
                    <td class="px-2 py-2.5 text-center font-mono text-slate-700">{{ rpt_fmt_min($row['trabalhado_min']) }}</td>
                    <td class="px-2 py-2.5 text-center font-mono {{ $row['extra_50_min'] > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300' }}">
                        {{ $row['extra_50_min'] > 0 ? rpt_fmt_min($row['extra_50_min']) : '—' }}
                    </td>
                    <td class="px-2 py-2.5 text-center font-mono {{ $row['extra_100_min'] > 0 ? 'text-violet-600 font-semibold' : 'text-slate-300' }}">
                        {{ $row['extra_100_min'] > 0 ? rpt_fmt_min($row['extra_100_min']) : '—' }}
                    </td>
                    <td class="px-2 py-2.5 text-center font-mono {{ $row['extra_noc_min'] > 0 ? 'text-sky-600 font-semibold' : 'text-slate-300' }}">
                        {{ $row['extra_noc_min'] > 0 ? rpt_fmt_min($row['extra_noc_min']) : '—' }}
                    </td>
                    <td class="px-2 py-2.5 text-center font-mono {{ $row['falta_min'] > 0 ? 'text-rose-600 font-semibold' : 'text-slate-300' }}">
                        {{ $row['falta_min'] > 0 ? rpt_fmt_min($row['falta_min']) : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-slate-800 text-white font-bold text-xs">
                    <td class="px-3 py-2.5" colspan="5">TOTAIS — {{ count($rows) }} colaborador(es)</td>
                    <td class="px-2 py-2.5 text-center">{{ $totalDiasTrab }}</td>
                    <td class="px-2 py-2.5 text-center text-rose-300">{{ $totalDiasFalta > 0 ? $totalDiasFalta : '—' }}</td>
                    <td class="px-2 py-2.5 text-center font-mono">{{ rpt_fmt_min($totalTrabalhado) }}</td>
                    <td class="px-2 py-2.5 text-center font-mono text-amber-300">{{ $totalExtra50 > 0 ? rpt_fmt_min($totalExtra50) : '—' }}</td>
                    <td class="px-2 py-2.5 text-center font-mono text-violet-300">{{ $totalExtra100 > 0 ? rpt_fmt_min($totalExtra100) : '—' }}</td>
                    <td class="px-2 py-2.5 text-center font-mono text-sky-300">{{ $totalExtraNoc > 0 ? rpt_fmt_min($totalExtraNoc) : '—' }}</td>
                    <td class="px-2 py-2.5 text-center font-mono text-rose-300">{{ $totalFalta > 0 ? rpt_fmt_min($totalFalta) : '—' }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@else
<div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
    <svg class="mx-auto mb-3 w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
    </svg>
    <p class="text-slate-400 text-sm">Nenhum colaborador encontrado para o período e filtros selecionados.</p>
</div>
@endif

@endsection
