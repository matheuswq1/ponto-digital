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

    {{-- Escala de trabalho --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <h2 class="text-sm font-semibold text-slate-700">Escala de trabalho</h2>
        </div>
        <p class="text-xs text-slate-400 mb-4">Defina o horário esperado para gerar alertas de atraso, ausência e hora extra. Tolerância padrão: 5 min.</p>

        @php $ws = $employee->workSchedule; @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horário de entrada</label>
                <input type="time" name="ws_entry_time" value="{{ old('ws_entry_time', $ws?->entry_time ?? '08:00') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horário de saída</label>
                <input type="time" name="ws_exit_time" value="{{ old('ws_exit_time', $ws?->exit_time ?? '17:00') }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                    Intervalo de almoço (min)
                    <span class="text-slate-400 font-normal ml-1">— opcional</span>
                </label>
                <input type="number" name="ws_lunch_minutes" min="0" max="480" placeholder="Ex: 60"
                       value="{{ old('ws_lunch_minutes', $ws?->lunch_minutes) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Deixe em branco se o almoço não tem duração mínima fixa. O sistema calcula pelo registro de ponto.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tolerância (minutos)</label>
                <input type="number" name="ws_tolerance" min="0" max="60"
                       value="{{ old('ws_tolerance', $ws?->tolerance_minutes ?? 5) }}"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-2">Dias de trabalho</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','0'=>'Dom'] as $val => $lbl)
                        @php $checked = in_array((int)$val, $ws?->work_days ?? [1,2,3,4,5]); @endphp
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="checkbox" name="ws_work_days[]" value="{{ $val }}"
                                   {{ $checked ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-200">
                            {{ $lbl }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Notificações --}}
        <div class="mt-4 pt-4 border-t border-slate-100">
            <p class="text-xs font-medium text-slate-600 mb-3">Alertas por push notification</p>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-xs cursor-pointer">
                    <input type="checkbox" name="ws_notify_late" value="1"
                           {{ old('ws_notify_late', $ws?->notify_late ?? true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-200">
                    Atraso na entrada
                </label>
                <label class="flex items-center gap-2 text-xs cursor-pointer">
                    <input type="checkbox" name="ws_notify_absence" value="1"
                           {{ old('ws_notify_absence', $ws?->notify_absence ?? true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-200">
                    Ausência no dia
                </label>
                <label class="flex items-center gap-2 text-xs cursor-pointer">
                    <input type="checkbox" name="ws_notify_overtime" value="1"
                           {{ old('ws_notify_overtime', $ws?->notify_overtime ?? true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-200">
                    Hora extra (>30 min)
                </label>
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

{{-- Redefinir senha --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mt-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100">
            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-slate-700">Redefinir senha de acesso</h2>
            <p class="text-xs text-slate-400">A nova senha será aplicada imediatamente no app do colaborador.</p>
        </div>
    </div>
    <form method="post" action="{{ route('painel.employees.reset-password', $employee) }}" class="space-y-4 max-w-sm">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nova senha <span class="text-rose-500">*</span></label>
            <input type="password" name="password" required minlength="6"
                   autocomplete="new-password"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Confirmar nova senha <span class="text-rose-500">*</span></label>
            <input type="password" name="password_confirmation" required minlength="6"
                   autocomplete="new-password"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none">
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
            </svg>
            Redefinir senha
        </button>
    </form>
</div>

</div>

@endsection
