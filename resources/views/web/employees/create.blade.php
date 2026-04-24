@extends('web.layout')
@section('title', 'Novo Colaborador')
@section('page-title', 'Novo Colaborador')

@section('content')

<div class="max-w-3xl">

@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="post" action="{{ route('painel.employees.store') }}" class="space-y-6">
    @csrf

    {{-- Dados pessoais --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Dados pessoais</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nome completo <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail <span class="text-rose-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CPF <span class="text-rose-500">*</span></label>
                <input type="text" name="cpf" value="{{ old('cpf') }}" required placeholder="000.000.000-00" maxlength="14"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">PIS / NIT</label>
                <input type="text" name="pis" value="{{ old('pis') }}" placeholder="000.00000.00-0"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Senha inicial <span class="text-rose-500">*</span></label>
                <input type="password" name="password" required minlength="6"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
        </div>
    </div>

    {{-- Dados profissionais --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Dados profissionais</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cargo <span class="text-rose-500">*</span></label>
                <input type="text" name="cargo" value="{{ old('cargo') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Empresa <span class="text-rose-500">*</span></label>
                <select name="company_id" id="employee_company_id" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                    <option value="">Selecione...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Departamento</label>
                <select name="department_id" id="employee_department_id"
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                    <option value="">— Nenhum —</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" data-company="{{ $d->company_id }}" @selected(old('department_id') == $d->id)>
                            {{ $d->company?->name ?? '' }} — {{ $d->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Configure departamentos e escalas no menu <strong>Departamentos</strong>.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de contrato <span class="text-rose-500">*</span></label>
                <select name="contract_type" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                    <option value="clt"       @selected(old('contract_type')=='clt')>CLT</option>
                    <option value="pj"        @selected(old('contract_type')=='pj')>PJ</option>
                    <option value="estagio"   @selected(old('contract_type')=='estagio')>Estágio</option>
                    <option value="temporario" @selected(old('contract_type')=='temporario')>Temporário</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horas semanais <span class="text-rose-500">*</span></label>
                <input type="number" name="weekly_hours" value="{{ old('weekly_hours', 44) }}" min="1" max="60" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Data de admissão <span class="text-rose-500">*</span></label>
                <input type="date" name="admission_date" value="{{ old('admission_date') }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Matrícula</label>
                <input type="text" name="registration_number" value="{{ old('registration_number') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
        </div>
    </div>

    {{-- Ações --}}
    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">
            Cadastrar colaborador
        </button>
        <a href="{{ route('painel.employees.index') }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>
    </div>
</form>
</div>
<script>
(function () {
    const company = document.getElementById('employee_company_id');
    const dept    = document.getElementById('employee_department_id');
    if (!company || !dept) return;
    function filterDept() {
        const cid = company.value;
        dept.querySelectorAll('option[data-company]').forEach(function (o) {
            o.hidden = cid && o.dataset.company !== cid;
        });
    }
    company.addEventListener('change', function () { filterDept(); dept.value = ''; });
    filterDept();
})();
</script>
@endsection
