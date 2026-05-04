@extends('web.layout')
@section('title', 'Holerites')
@section('page-title', 'Holerites')

@section('content')
@php
    $months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
               7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
@endphp

{{-- Flash --}}
@if(session('success'))
<div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

{{-- Filtros --}}
<form method="get" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Empresa</label>
            <select name="company_id" id="filter-company" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Todas</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(request('company_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Colaborador</label>
            <select name="employee_id" id="filter-employee" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Todos</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->user?->name ?? 'Funcionário #'.$e->id }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Ano</label>
            <select name="year" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                @foreach(range(now()->year, now()->year - 4) as $y)
                    <option value="{{ $y }}" @selected(request('year', now()->year) == $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Mês</label>
            <select name="month" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Todos</option>
                @foreach($months as $n => $label)
                    <option value="{{ $n }}" @selected(request('month') == $n)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="flex items-center gap-2 mt-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700">Filtrar</button>
        <a href="{{ route('painel.payslips.index') }}" class="text-sm text-slate-500 py-2 px-2 hover:text-slate-700">Limpar</a>
        <div class="flex-1"></div>
        <a href="{{ route('painel.payslips.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-emerald-700">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
            Enviar holerites
        </a>
    </div>
</form>

{{-- Tabela --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($payslips->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
            <svg class="w-12 h-12 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
            </svg>
            <p class="text-sm font-medium">Nenhum holerite encontrado</p>
            <p class="text-xs mt-1">Ajuste os filtros ou envie novos holerites</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Colaborador</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Referência</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Descrição</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Tamanho</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Enviado em</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($payslips as $p)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="font-medium text-slate-800">{{ $p->employee->user?->name ?? 'N/A' }}</div>
                        @if(auth()->user()->isAdmin())
                        <div class="text-xs text-slate-400">{{ $p->company->name }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                            {{ $p->getReferenceLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-slate-500 hidden sm:table-cell">{{ $p->description ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden md:table-cell">{{ $p->getFileSizeFormatted() ?: '—' }}</td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden lg:table-cell">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ $p->file_url }}" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 px-2.5 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Baixar
                            </a>
                            <form method="post" action="{{ route('painel.payslips.destroy', $p) }}"
                                  onsubmit="return confirm('Remover este holerite? Esta ação não pode ser desfeita.')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-600 hover:text-rose-800 px-2.5 py-1.5 rounded-lg hover:bg-rose-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                    Remover
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($payslips->hasPages())
        <div class="px-5 py-3 border-t border-slate-100">{{ $payslips->links() }}</div>
        @endif
    @endif
</div>

<script>
// Quando mudar empresa no filtro, recarrega colaboradores via AJAX
document.getElementById('filter-company')?.addEventListener('change', function() {
    const cid = this.value;
    const sel = document.getElementById('filter-employee');
    sel.innerHTML = '<option value="">Todos</option>';
    if (!cid) return;
    fetch(`{{ url('/painel/holerites/colaboradores') }}/${cid}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(e => {
                const o = document.createElement('option');
                o.value = e.id; o.textContent = e.name;
                sel.appendChild(o);
            });
        });
});
</script>
@endsection
