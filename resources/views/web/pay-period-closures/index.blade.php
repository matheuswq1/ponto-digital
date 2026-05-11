@extends('web.layout')
@section('title', 'Fechos do espelho')
@section('page-title', 'Fechos do espelho de ponto')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-slate-500">Feche um período para que os colaboradores aceitem ou rejeitem o espelho na app. Pode fechar para toda a empresa, só por departamento(s) ou colaboradores à escolha. Enquanto todos estiverem pendentes (ninguém aceitou nem contestou), pode usar «Excluir» na tabela para remover o fecho; até lá, o colaborador continua a ver o lembrete ao abrir a app.</p>
        @if(! auth()->user()->isAdmin() && auth()->user()->company)
            <p class="text-xs text-slate-400 mt-1">{{ auth()->user()->company->name }}</p>
        @endif
    </div>
</div>

@if(count($companies) > 1 && auth()->user()->isAdmin())
<div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3 mb-5 flex flex-wrap items-center gap-3">
    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Empresa (lista + departamentos no formulário)</span>
    <form method="get" class="flex items-center gap-2">
        <select name="company_id" onchange="this.form.submit()"
                class="text-xs border border-slate-300 rounded-lg px-2 py-1.5 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
            <option value="">— Escolher empresa —</option>
            @foreach($companies as $co)
                <option value="{{ $co->id }}" @selected((string)$companyId === (string)$co->id)>{{ $co->name }}</option>
            @endforeach
        </select>
    </form>
</div>
@endif

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
    {{ session('error') }}
</div>
@endif

@if ($errors->any())
<div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc list-inside space-y-1">
        @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(auth()->user()->isAdmin() && ! $dataCompanyId && count($companies) > 1)
<div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
    Escolha uma empresa acima para carregar departamentos e colaboradores no formulário (fechos parciais).
</div>
@endif

@php
    $scopeOld = old('closure_scope', 'company');
@endphp

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">
    <h3 class="text-sm font-semibold text-slate-800 mb-4">Novo fecho</h3>
    <form method="post" action="{{ route('painel.pay-period-closures.store') }}" class="space-y-4">
        @csrf
        @if(auth()->user()->isAdmin())
        <div>
            <label for="company_id" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Empresa</label>
            <select name="company_id" id="company_id" required
                    data-reload-departments="{{ auth()->user()->isAdmin() && count($companies) > 1 ? '1' : '0' }}"
                    class="w-full max-w-md text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 outline-none @error('company_id') border-rose-400 @enderror">
                <option value="">— Selecionar —</option>
                @foreach($companies as $co)
                    <option value="{{ $co->id }}" @selected(old('company_id', $selectedCompanyForForm) == $co->id)>{{ $co->name }}</option>
                @endforeach
            </select>
            @error('company_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            @if(auth()->user()->isAdmin() && count($companies) > 1)
                <p class="text-[11px] text-slate-400 mt-1">Ao mudar a empresa, a página actualiza para carregar departamentos e colaboradores correctos.</p>
            @endif
        </div>
        @endif

        <fieldset class="space-y-2">
            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Quem entra neste fecho</legend>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="radio" name="closure_scope" value="company" class="text-indigo-600 focus:ring-indigo-500" @checked($scopeOld === 'company')>
                Toda a empresa (todos os colaboradores activos)
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="radio" name="closure_scope" value="departments" class="text-indigo-600 focus:ring-indigo-500" @checked($scopeOld === 'departments')>
                Por departamento(s) — útil quando calendários ou datas de fecho diferem por equipa
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="radio" name="closure_scope" value="employees" class="text-indigo-600 focus:ring-indigo-500" @checked($scopeOld === 'employees')>
                Por colaborador(es) — escolha manual na lista
            </label>
            @error('closure_scope')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        </fieldset>

        <div id="closure-departments-box" class="@if($scopeOld !== 'departments') hidden @endif space-y-1">
            <label for="department_ids" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Departamentos</label>
            @if($departments->isEmpty())
                <p class="text-sm text-slate-400">Sem departamentos activos para esta empresa — cadastre departamentos ou escolha outro alcance.</p>
            @else
                <select name="department_ids[]" id="department_ids" multiple size="{{ min(8, max(3, $departments->count())) }}"
                        class="w-full max-w-md text-sm border border-slate-300 rounded-lg px-2 py-1 bg-white focus:ring-2 focus:ring-indigo-200 outline-none @error('department_ids') border-rose-400 @enderror">
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected(collect(old('department_ids', []))->contains($d->id))>{{ $d->name }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400">Mantenha Ctrl (Windows) ou Cmd (Mac) para seleccionar vários.</p>
            @endif
            @error('department_ids')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div id="closure-employees-box" class="@if($scopeOld !== 'employees') hidden @endif space-y-1">
            <label for="employee_ids" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Colaboradores</label>
            @if($employeesForClosure->isEmpty())
                <p class="text-sm text-slate-400">Sem colaboradores activos listados para esta empresa.</p>
            @else
                <select name="employee_ids[]" id="employee_ids" multiple size="{{ min(12, max(4, min($employeesForClosure->count(), 10))) }}"
                        class="w-full max-w-xl text-sm border border-slate-300 rounded-lg px-2 py-1 bg-white focus:ring-2 focus:ring-indigo-200 outline-none @error('employee_ids') border-rose-400 @enderror">
                    @foreach($employeesForClosure as $emp)
                        @php $label = $emp->user->name ?? ('#'.$emp->id); @endphp
                        <option value="{{ $emp->id }}" @selected(collect(old('employee_ids', []))->contains($emp->id))>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400">Ctrl/Cmd para várias linhas. Colaboradores sem departamento não aparecem em «Por departamento».</p>
            @endif
            @error('employee_ids')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-4">
            <div>
                <label for="period_start" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Data inicial</label>
                <input type="text" name="period_start" id="period_start" value="{{ old('period_start') }}" required
                       inputmode="numeric" maxlength="10" placeholder="dd/mm/aaaa" autocomplete="off"
                       class="text-sm border border-slate-300 rounded-lg px-3 py-2 w-36 focus:ring-2 focus:ring-indigo-200 outline-none @error('period_start') border-rose-400 @enderror">
                <p class="text-[11px] text-slate-400 mt-1">Digite no formato dia/mês/ano.</p>
            </div>
            <div>
                <label for="period_end" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Data final</label>
                <input type="text" name="period_end" id="period_end" value="{{ old('period_end') }}" required
                       inputmode="numeric" maxlength="10" placeholder="dd/mm/aaaa" autocomplete="off"
                       class="text-sm border border-slate-300 rounded-lg px-3 py-2 w-36 focus:ring-2 focus:ring-indigo-200 outline-none @error('period_end') border-rose-400 @enderror">
            </div>
        </div>
        <div>
            <label for="notes" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Observações (opcional)</label>
            <textarea name="notes" id="notes" rows="2" maxlength="5000"
                      class="w-full max-w-2xl text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none @error('notes') border-rose-400 @enderror">{{ old('notes') }}</textarea>
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold px-4 py-2 hover:bg-indigo-700 transition">
            Fechar período
        </button>
    </form>
</div>

@if($closures->isEmpty())
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-sm">
    <svg class="mx-auto w-12 h-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
    </svg>
    <h3 class="text-lg font-semibold text-slate-700 mb-1">Nenhum fecho registado</h3>
    <p class="text-sm text-slate-400">Use o formulário acima para fechar o primeiro período.</p>
</div>
@else
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    @if(auth()->user()->isAdmin())
                    <th class="px-4 py-3">Empresa</th>
                    @endif
                    <th class="px-4 py-3">Período</th>
                    <th class="px-4 py-3 text-center">Colab.</th>
                    <th class="px-4 py-3">Fechado</th>
                    <th class="px-4 py-3 text-center">Pend.</th>
                    <th class="px-4 py-3 text-center">Aprov.</th>
                    <th class="px-4 py-3 text-center">Rej.</th>
                    <th class="px-4 py-3 text-right">Acções</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($closures as $c)
                <tr class="hover:bg-slate-50/80">
                    @if(auth()->user()->isAdmin())
                    <td class="px-4 py-3 text-slate-700">{{ $c->company->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-slate-800 font-medium">
                        {{ $c->period_start->format('d/m/Y') }} — {{ $c->period_end->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600 tabular-nums">{{ $c->people_total }}</td>
                    <td class="px-4 py-3 text-slate-600">
                        <span class="block">{{ $c->closed_at?->locale('pt_BR')->translatedFormat('d M Y, H:i') }}</span>
                        <span class="text-xs text-slate-400">{{ $c->closedByUser->name ?? '—' }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex min-w-[2rem] justify-center rounded-full bg-amber-100 text-amber-800 text-xs font-semibold px-2 py-0.5">{{ $c->pending_count }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex min-w-[2rem] justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold px-2 py-0.5">{{ $c->approved_count }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex min-w-[2rem] justify-center rounded-full bg-rose-100 text-rose-800 text-xs font-semibold px-2 py-0.5">{{ $c->rejected_count }}</span>
                    </td>
                    <td class="px-4 py-3 text-right align-middle">
                        @if($c->pending_count > 0 && $c->approved_count == 0 && $c->rejected_count == 0)
                            <form method="post" action="{{ route('painel.pay-period-closures.destroy', $c) }}" class="inline"
                                  onsubmit="return confirm('Remover este fecho? Os colaboradores deixam de ver este espelho pendente na app.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:underline">
                                    Excluir
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($closures->hasPages())
    <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/50">
        {{ $closures->links() }}
    </div>
    @endif
</div>
@endif

<script>
(function () {
    function maskBrDate(el) {
        el.addEventListener('input', function () {
            var d = el.value.replace(/\D/g, '').slice(0, 8);
            if (d.length <= 2) el.value = d;
            else if (d.length <= 4) el.value = d.slice(0, 2) + '/' + d.slice(2);
            else el.value = d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
        });
    }
    var s = document.getElementById('period_start');
    var e = document.getElementById('period_end');
    if (s) maskBrDate(s);
    if (e) maskBrDate(e);

    function toggleClosureScope() {
        var checked = document.querySelector('input[name="closure_scope"]:checked');
        var v = checked ? checked.value : 'company';
        var db = document.getElementById('closure-departments-box');
        var eb = document.getElementById('closure-employees-box');
        if (db) db.classList.toggle('hidden', v !== 'departments');
        if (eb) eb.classList.toggle('hidden', v !== 'employees');
    }
    document.querySelectorAll('input[name="closure_scope"]').forEach(function (r) {
        r.addEventListener('change', toggleClosureScope);
    });
    toggleClosureScope();

    var co = document.getElementById('company_id');
    if (co && co.getAttribute('data-reload-departments') === '1') {
        co.addEventListener('change', function () {
            var v = this.value;
            if (!v) return;
            var url = new URL(window.location.href);
            url.searchParams.set('company_id', v);
            window.location.href = url.toString();
        });
    }
})();
</script>

@endsection
