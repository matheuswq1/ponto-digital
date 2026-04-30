@extends('web.layout')
@section('title', 'Novo departamento')
@section('page-title', 'Novo departamento')

@section('content')
<div class="max-w-2xl">
@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
</div>
@endif

<form method="post" action="{{ route('painel.departments.store') }}" class="space-y-6">
    @csrf
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Empresa <span class="text-rose-500">*</span></label>
            <select name="company_id" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Selecione…</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nome do departamento <span class="text-rose-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Entrada</label>
                <input type="time" name="entry_time" value="{{ old('entry_time', '08:00') }}" step="60"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Saída</label>
                <input type="time" name="exit_time" value="{{ old('exit_time', '18:00') }}" step="60"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Intervalo padrão (minutos)</label>
                <input type="number" name="lunch_minutes" value="{{ old('lunch_minutes', 60) }}" min="0" max="300"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tolerância (min)</label>
                <input type="number" name="tolerance_minutes" value="{{ old('tolerance_minutes', 10) }}" min="0" max="120"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
        </div>

        @include('web.departments._lunch_by_day', ['department' => null, 'defaultLunch' => old('lunch_minutes', 60)])

        <div>
            <p class="text-xs font-medium text-slate-600 mb-2">Dias de trabalho</p>
            <div class="flex flex-wrap gap-3 text-sm">
                @php $wd = old('work_days', [1,2,3,4,5]); @endphp
                @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',0=>'Dom'] as $val => $label)
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" name="work_days[]" value="{{ $val }}" @checked(in_array($val, (array)$wd, true)) class="rounded border-slate-300 text-indigo-600">
                    {{ $label }}
                </label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', true)) class="rounded border-slate-300 text-indigo-600">
                Departamento ativo
            </label>
        </div>

        <div class="border-t border-slate-100 pt-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Controle de registro</p>
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800 cursor-pointer">
                        <input type="hidden" name="app_punch_disabled" value="0">
                        <input type="checkbox" name="app_punch_disabled" value="1"
                               @checked(old('app_punch_disabled', false))
                               class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                        Bloquear registro pelo app — somente totem
                    </label>
                    <p class="mt-1 text-xs text-slate-500">
                        Quando ativado, os colaboradores deste departamento não poderão registrar o ponto pelo aplicativo.
                        O ponto ficará disponível apenas pelo totem da empresa.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700">Guardar</button>
        <a href="{{ route('painel.departments.index') }}" class="text-sm text-slate-600 py-2.5">Cancelar</a>
    </div>
</form>
</div>
@endsection
