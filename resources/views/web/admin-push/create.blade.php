@extends('web.layout')
@section('title', 'Notificar app')
@section('page-title', 'Notificar app (push)')

@section('content')
<div class="max-w-2xl">

@if(!$companyId && auth()->user()->isAdmin())
<div class="mb-5 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
    Não há empresa activa para carregar destinatários. Cadastre uma empresa ou seleccione-a abaixo após criar.
</div>
@endif

<form method="post" action="{{ route('painel.admin-push.store') }}" class="space-y-5" id="admin-push-form">
    @csrf

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Empresa <span class="text-rose-500">*</span></label>
            <select name="company_id" id="company-sel" required
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                <option value="">Selecione a empresa…</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected((int) old('company_id', $companyId) === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @else
        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
        @endif

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Título <span class="text-rose-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="120"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none"
                   placeholder="Ex.: Aviso importante">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mensagem <span class="text-rose-500">*</span></label>
            <textarea name="body" rows="4" required maxlength="500"
                      class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none resize-y"
                      placeholder="Texto que aparece na notificação">{{ old('body') }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Máximo 500 caracteres. Apenas utilizadores com app instalada e token FCM registado recebem o push.</p>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-xs font-semibold text-slate-600 mb-2">Destinatários</legend>
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="radio" name="target" value="all" class="mt-1" @checked(old('target', 'all') === 'all')>
                <span class="text-sm text-slate-700">Todos os colaboradores activos com conta na app (empresa seleccionada)</span>
            </label>
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="radio" name="target" value="department" class="mt-1" @checked(old('target') === 'department')>
                <span class="text-sm text-slate-700">Apenas um departamento (setor)</span>
            </label>
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="radio" name="target" value="user" class="mt-1" @checked(old('target') === 'user')>
                <span class="text-sm text-slate-700">Um colaborador específico</span>
            </label>
        </fieldset>

        <div id="row-department" class="{{ old('target') === 'department' ? '' : 'hidden' }}">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Departamento <span class="text-rose-500">*</span></label>
            <select name="department_id" id="department-sel"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                <option value="">Selecione…</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected((int) old('department_id') === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>

        <div id="row-employee" class="{{ old('target') === 'user' ? '' : 'hidden' }}">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Colaborador <span class="text-rose-500">*</span></label>
            <select name="employee_id" id="employee-sel"
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                <option value="">Selecione…</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @selected((int) old('employee_id') === $e->id)>{{ $e->user?->name ?? 'Colaborador #'.$e->id }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700">
            Enviar notificação
        </button>
        <a href="{{ route('painel.dashboard') }}" class="text-sm text-slate-500 py-2.5 px-2">Cancelar</a>
    </div>
</form>
</div>

<script>
(function () {
    const metaUrl = @json(route('painel.admin-push.meta'));
    const isAdmin = @json(auth()->user()->isAdmin());
    const companySel = document.getElementById('company-sel');
    const deptSel = document.getElementById('department-sel');
    const empSel = document.getElementById('employee-sel');
    const rowDept = document.getElementById('row-department');
    const rowEmp = document.getElementById('row-employee');

    function fillSelect(sel, items, valueKey, labelKey) {
        const cur = sel.value;
        sel.innerHTML = '<option value="">Selecione…</option>';
        items.forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = row[valueKey];
            opt.textContent = row[labelKey];
            sel.appendChild(opt);
        });
        if ([...sel.options].some(o => o.value === cur)) sel.value = cur;
    }

    async function loadMeta(companyId) {
        if (!companyId || !deptSel || !empSel) return;
        const url = metaUrl + '?company_id=' + encodeURIComponent(companyId);
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            fillSelect(deptSel, data.departments || [], 'id', 'name');
            fillSelect(empSel, data.employees || [], 'id', 'name');
        } catch (e) {}
    }

    function syncTargetRows() {
        const t = document.querySelector('input[name="target"]:checked')?.value || 'all';
        if (rowDept) rowDept.classList.toggle('hidden', t !== 'department');
        if (rowEmp) rowEmp.classList.toggle('hidden', t !== 'user');
        if (deptSel) deptSel.required = (t === 'department');
        if (empSel) empSel.required = (t === 'user');
    }

    document.querySelectorAll('input[name="target"]').forEach(function (r) {
        r.addEventListener('change', syncTargetRows);
    });

    if (isAdmin && companySel) {
        companySel.addEventListener('change', function () {
            loadMeta(this.value);
        });
    }

    syncTargetRows();
})();
</script>
@endsection
