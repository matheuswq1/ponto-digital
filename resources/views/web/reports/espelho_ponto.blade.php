@extends('web.layout')
@section('title', 'Espelho de Ponto')
@section('page-title', 'Espelho de Ponto')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-800">Espelho de Ponto</h1>
        <p class="text-sm text-slate-400 mt-0.5">Detalhe diário de registros por colaborador</p>
    </div>
</div>

{{-- Filtros --}}
<form method="get" id="espelho-form" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Empresa</label>
            <select name="company_id" id="esp-company" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Selecione…</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(request('company_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Colaborador</label>
            <select name="employee_id" id="esp-employee" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white" required>
                <option value="">Selecione…</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->user?->name ?? 'Funcionário #'.$e->id }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">De</label>
            @include('web.components.date-input', ['name' => 'date_from', 'value' => $dateFrom])
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Até</label>
            @include('web.components.date-input', ['name' => 'date_to', 'value' => $dateTo])
        </div>
    </div>
    <div class="flex items-center gap-2 mt-3 flex-wrap">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-semibold px-5 py-2 rounded-lg hover:bg-indigo-700">
            Gerar espelho
        </button>
        @if(isset($emp) && $rows->count() > 0)
        {{-- Dropdown exportar --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-2 bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-slate-800">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Exportar
                <svg class="w-3.5 h-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </button>
            <div x-show="open" @click.outside="open=false" x-cloak
                 class="absolute left-0 mt-1 w-40 bg-white rounded-xl border border-slate-200 shadow-xl z-10 overflow-hidden">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
                   class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    Baixar PDF
                </a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
                   class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 border-t border-slate-100">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125-.504 1.125-1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h.008v.008h-.008v-.008Zm0 3.75h.008v.008h-.008V11.25Zm0 3.75h.008v.008h-.008V15Zm0 3.75h.008v.008h-.008v-.008Z"/></svg>
                    Baixar CSV
                </a>
            </div>
        </div>
        @endif
    </div>
</form>

@if(isset($emp) && $rows->count() > 0)
@php
    $totWorked  = $rows->sum('worked_m');
    $totDiff    = $rows->sum('diff_m');
    $totPresent = $rows->where('status', 'Presente')->count();
    $totAbsent  = $rows->where('status', 'Falta')->count();
    $fmtMin     = fn(int $m) => $m === 0 ? '00:00' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
@endphp

{{-- Card do colaborador --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4 flex items-center gap-4">
    <div class="w-11 h-11 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-lg flex-shrink-0">
        {{ strtoupper(substr($emp->user?->name ?? 'C', 0, 1)) }}
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-bold text-slate-800">{{ $emp->user?->name ?? '—' }}</p>
        <p class="text-xs text-slate-400">{{ $emp->cargo ?? '' }} · {{ $emp->dept?->name ?? $emp->department ?? '' }} · Mat. {{ $emp->registration_number ?? '—' }}</p>
    </div>
    <div class="flex gap-4 text-center">
        <div><p class="text-lg font-bold text-emerald-600">{{ $totPresent }}</p><p class="text-xs text-slate-400">Presenças</p></div>
        <div><p class="text-lg font-bold text-rose-600">{{ $totAbsent }}</p><p class="text-xs text-slate-400">Faltas</p></div>
        <div><p class="text-lg font-bold text-slate-800">{{ $fmtMin($totWorked) }}</p><p class="text-xs text-slate-400">H. Trabalhadas</p></div>
        <div>
            <p class="text-lg font-bold {{ $totDiff >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ ($totDiff >= 0 ? '+' : '-').$fmtMin(abs($totDiff)) }}
            </p>
            <p class="text-xs text-slate-400">Saldo</p>
        </div>
    </div>
</div>

{{-- Tabela diária --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-800 text-slate-100 text-xs">
            <tr>
                <th class="px-4 py-3 text-left font-semibold">Data</th>
                <th class="px-3 py-3 text-center font-semibold">Entradas</th>
                <th class="px-3 py-3 text-center font-semibold">Saídas</th>
                <th class="px-3 py-3 text-center font-semibold">Trabalhado</th>
                <th class="px-3 py-3 text-center font-semibold hidden md:table-cell">Esperado</th>
                <th class="px-3 py-3 text-center font-semibold">Diferença</th>
                <th class="px-3 py-3 text-center font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($rows as $row)
            @php
                $bg = match($row['status']) {
                    'Feriado' => 'bg-violet-50',
                    'Folga'   => 'bg-slate-50',
                    'Falta'   => 'bg-rose-50',
                    'Futuro'  => 'bg-slate-50 opacity-50',
                    default   => '',
                };
                $statusBadge = match($row['status']) {
                    'Presente' => 'bg-emerald-100 text-emerald-700',
                    'Falta'    => 'bg-rose-100 text-rose-700',
                    'Feriado'  => 'bg-violet-100 text-violet-700',
                    'Folga'    => 'bg-slate-100 text-slate-500',
                    default    => 'bg-slate-100 text-slate-400',
                };
            @endphp
            <tr class="{{ $bg }} hover:bg-indigo-50 transition-colors">
                <td class="px-4 py-2.5 font-medium text-slate-700 text-xs">{{ $row['date_fmt'] }}</td>
                <td class="px-3 py-2.5 text-center text-slate-600 font-mono text-xs">
                    {{ $row['entries']->count() > 0 ? $row['entries']->implode(' · ') : '—' }}
                </td>
                <td class="px-3 py-2.5 text-center text-slate-600 font-mono text-xs">
                    {{ $row['exits']->count() > 0 ? $row['exits']->implode(' · ') : '—' }}
                </td>
                <td class="px-3 py-2.5 text-center font-semibold font-mono text-xs {{ $row['worked_m'] > 0 ? 'text-slate-800' : 'text-slate-300' }}">
                    {{ $row['worked_m'] > 0 ? $row['worked'] : '—' }}
                </td>
                <td class="px-3 py-2.5 text-center text-slate-400 font-mono text-xs hidden md:table-cell">
                    {{ in_array($row['status'], ['Folga','Feriado','Futuro']) ? '—' : $row['expected'] }}
                </td>
                <td class="px-3 py-2.5 text-center font-bold font-mono text-xs">
                    @if($row['diff'] !== '—')
                        <span class="{{ str_starts_with($row['diff'], '+') ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $row['diff'] }}
                        </span>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="px-3 py-2.5 text-center">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBadge }}">
                        {{ $row['status'] }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-slate-800 text-white text-xs">
                <td class="px-4 py-3 font-semibold" colspan="3">TOTAIS — {{ $totPresent }} presença(s) · {{ $totAbsent }} falta(s)</td>
                <td class="px-3 py-3 text-center font-bold font-mono">{{ $fmtMin($totWorked) }}</td>
                <td class="hidden md:table-cell"></td>
                <td class="px-3 py-3 text-center font-bold font-mono {{ $totDiff >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ ($totDiff >= 0 ? '+' : '-').$fmtMin(abs($totDiff)) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

@elseif(request('employee_id'))
<div class="bg-white rounded-xl border border-slate-200 p-10 text-center text-slate-400">
    <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
    <p class="font-medium text-sm">Nenhum registro encontrado no período.</p>
</div>
@else
<div class="bg-white rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-400">
    <svg class="w-10 h-10 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
    </svg>
    <p class="font-medium text-sm">Selecione um colaborador e o período desejado</p>
</div>
@endif

<script>
// Carregar colaboradores ao mudar empresa
document.getElementById('esp-company')?.addEventListener('change', function() {
    const sel = document.getElementById('esp-employee');
    sel.innerHTML = '<option value="">Selecione…</option>';
    if (!this.value) return;
    fetch(`{{ url('/painel/holerites/colaboradores') }}/${this.value}`)
        .then(r => r.json())
        .then(data => data.forEach(e => {
            const o = document.createElement('option');
            o.value = e.id; o.textContent = e.name;
            sel.appendChild(o);
        }));
});

// Alpine.js fallback para o dropdown (caso não esteja carregado)
if (!window.Alpine) {
    document.querySelectorAll('[x-data]').forEach(el => {
        const btn = el.querySelector('button');
        const menu = el.querySelector('[x-show]');
        if (!menu) return;
        menu.style.display = 'none';
        btn?.addEventListener('click', e => {
            e.stopPropagation();
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', () => { menu.style.display = 'none'; });
    });
}
</script>
@endsection
