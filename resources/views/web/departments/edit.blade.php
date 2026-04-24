@extends('web.layout')
@section('title', 'Editar departamento')
@section('page-title', 'Editar departamento')

@section('content')
<div class="max-w-2xl">
@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
</div>
@endif

<form method="post" action="{{ route('painel.departments.update', $department) }}" class="space-y-6">
    @csrf
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Empresa <span class="text-rose-500">*</span></label>
            <select name="company_id" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(old('company_id', $department->company_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nome do departamento <span class="text-rose-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $department->name) }}" required maxlength="120"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Entrada</label>
                <input type="time" name="entry_time" value="{{ old('entry_time', $department->entry_time ? \Carbon\Carbon::parse($department->entry_time)->format('H:i') : '') }}" step="60"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Saída</label>
                <input type="time" name="exit_time" value="{{ old('exit_time', $department->exit_time ? \Carbon\Carbon::parse($department->exit_time)->format('H:i') : '') }}" step="60"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Intervalo padrão (minutos)</label>
                <input type="number" name="lunch_minutes" value="{{ old('lunch_minutes', $department->lunch_minutes) }}" min="0" max="300"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tolerância (min)</label>
                <input type="number" name="tolerance_minutes" value="{{ old('tolerance_minutes', $department->tolerance_minutes) }}" min="0" max="120"
                       class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
            </div>
        </div>

        @include('web.departments._lunch_by_day', ['department' => $department, 'defaultLunch' => old('lunch_minutes', $department->lunch_minutes)])

        <div>
            <p class="text-xs font-medium text-slate-600 mb-2">Dias de trabalho</p>
            <div class="flex flex-wrap gap-3 text-sm">
                @php $wd = old('work_days', $department->workDaysList()); @endphp
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
                <input type="checkbox" name="active" value="1" @checked(old('active', $department->active)) class="rounded border-slate-300 text-indigo-600">
                Departamento ativo
            </label>
        </div>
    </div>
    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-indigo-700">Atualizar</button>
        <a href="{{ route('painel.departments.index') }}" class="text-sm text-slate-600 py-2.5">Voltar</a>
    </div>
</form>
</div>
@endsection
