@extends('web.layout')

@section('title', 'Extrato banco de horas')
@section('page-title', 'Relatórios')

@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Extrato — Banco de horas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Saldo inicial do mês, movimentos e saldo final por colaborador</p>
    </div>
</div>

<form method="get" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Mês de referência</label>
            <input type="month" name="ym" value="{{ $ym }}"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
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
    <div class="flex flex-wrap items-center gap-3 mt-4">
        <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
            Gerar extrato
        </button>
        @if(count($sections) > 0)
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
           class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition">
            Exportar CSV
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
           class="px-5 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-lg transition">
            PDF
        </a>
        @endif
        <span class="text-xs text-slate-400">{{ $monthRef->locale('pt_BR')->translatedFormat('F \d\e Y') }}</span>
    </div>
</form>

@if(count($sections) === 0)
    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-500 text-sm">
        Nenhum movimento de banco de horas para o filtro seleccionado (ou saldos vazios).
    </div>
@else
    @foreach($sections as $sec)
    @php $e = $sec['employee']; @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50">
            <div>
                <p class="text-sm font-semibold text-slate-800">{{ $e->user?->name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ $e->company?->name ?? '—' }} @if($e->dept) · {{ $e->dept->name }} @endif</p>
            </div>
            <div class="text-right text-xs">
                <span class="text-slate-500">Saldo inicial:</span>
                <span class="font-mono font-semibold text-slate-800">{{ $sec['initialFmt'] }}</span>
                <span class="text-slate-400 mx-2">→</span>
                <span class="text-slate-500">Saldo final:</span>
                <span class="font-mono font-semibold text-slate-800">{{ $sec['closingFmt'] }}</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100 text-xs font-semibold text-slate-600 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Data ref.</th>
                        <th class="px-4 py-2 text-left">Tipo</th>
                        <th class="px-4 py-2 text-left">Descrição</th>
                        <th class="px-4 py-2 text-right">Movimento</th>
                        <th class="px-4 py-2 text-right">Saldo após</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sec['txRows'] as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['ref'] }}</td>
                        <td class="px-4 py-2">{{ $row['type'] }}</td>
                        <td class="px-4 py-2 text-slate-600 max-w-md truncate" title="{{ $row['desc'] }}">{{ $row['desc'] }}</td>
                        <td class="px-4 py-2 text-right font-mono {{ $row['minutes'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['signedFmt'] }}</td>
                        <td class="px-4 py-2 text-right font-mono text-slate-800">{{ $row['balFmt'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-slate-400 text-sm">Sem movimentos neste mês (saldo mantém-se).</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

@endsection
