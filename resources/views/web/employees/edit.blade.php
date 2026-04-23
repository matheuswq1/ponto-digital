@extends('web.layout')
@section('title', 'Editar Colaborador')
@section('page-title', 'Editar Colaborador')

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

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

<form method="post" action="{{ route('painel.employees.update', $employee) }}" class="space-y-6">
    @csrf

    {{-- Dados pessoais --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Dados pessoais</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nome completo <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $employee->user->name) }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">E-mail <span class="text-rose-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $employee->user->email) }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">CPF <span class="text-rose-500">*</span></label>
                <input type="text" name="cpf" value="{{ old('cpf', $employee->cpf) }}" required maxlength="14"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">PIS / NIT</label>
                <input type="text" name="pis" value="{{ old('pis', $employee->pis) }}"
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
                <input type="text" name="cargo" value="{{ old('cargo', $employee->cargo) }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Departamento</label>
                <input type="text" name="department" value="{{ old('department', $employee->department) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Empresa <span class="text-rose-500">*</span></label>
                <select name="company_id" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $employee->company_id) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de contrato <span class="text-rose-500">*</span></label>
                <select name="contract_type" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
                    <option value="clt"        @selected(old('contract_type', $employee->contract_type)=='clt')>CLT</option>
                    <option value="pj"         @selected(old('contract_type', $employee->contract_type)=='pj')>PJ</option>
                    <option value="estagio"    @selected(old('contract_type', $employee->contract_type)=='estagio')>Estágio</option>
                    <option value="temporario" @selected(old('contract_type', $employee->contract_type)=='temporario')>Temporário</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horas semanais <span class="text-rose-500">*</span></label>
                <input type="number" name="weekly_hours" value="{{ old('weekly_hours', $employee->weekly_hours) }}" min="1" max="60" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Data de admissão <span class="text-rose-500">*</span></label>
                <input type="date" name="admission_date" value="{{ old('admission_date', $employee->admission_date?->format('Y-m-d')) }}" required
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Matrícula</label>
                <input type="text" name="registration_number" value="{{ old('registration_number', $employee->registration_number) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
        </div>
    </div>

    {{-- Ações --}}
    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition">
            Salvar alterações
        </button>
        <a href="{{ route('painel.employees.show', $employee) }}" class="text-sm text-slate-500 hover:underline">Cancelar</a>

        <form method="post" action="{{ route('painel.employees.toggle', $employee) }}" class="ml-auto">
            @csrf
            <button type="submit"
                    class="text-sm font-medium px-4 py-2 rounded-lg border transition
                           {{ $employee->active
                               ? 'border-rose-300 text-rose-600 hover:bg-rose-50'
                               : 'border-emerald-300 text-emerald-600 hover:bg-emerald-50' }}">
                {{ $employee->active ? 'Desativar colaborador' : 'Reativar colaborador' }}
            </button>
        </form>
    </div>
</form>
</div>

@endsection
