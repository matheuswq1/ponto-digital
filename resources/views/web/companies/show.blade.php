@extends('web.layout')
@section('title', $company->name)
@section('page-title', $company->name)

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    <div>
        <p>{{ session('success') }}</p>
        @if(session('gestor_password_plain'))
            <p class="mt-2 font-mono text-xs bg-white/60 rounded px-2 py-1 border border-emerald-200">
                Palavra-passe gerada: <strong>{{ session('gestor_password_plain') }}</strong>
            </p>
            <p class="mt-1 text-xs text-emerald-800/90">Guarde esta palavra-passe — não será mostrada novamente.</p>
        @endif
    </div>
</div>
@endif

<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="{{ route('painel.companies.index') }}" class="text-sm text-slate-600 hover:underline">← Empresas</a>
    <span class="text-slate-300">|</span>
    <a href="{{ route('painel.companies.edit', $company) }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline">Editar dados</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Identificação</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-slate-500">CNPJ</dt><dd class="font-medium text-slate-800">{{ $company->cnpj }}</dd></div>
                <div><dt class="text-xs text-slate-500">Estado</dt><dd>
                    @if($company->active)
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Ativa</span>
                    @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inativa</span>
                    @endif
                </dd></div>
                @if($company->email)
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">E-mail</dt><dd class="text-slate-800">{{ $company->email }}</dd></div>
                @endif
                @if($company->phone)
                <div><dt class="text-xs text-slate-500">Telefone</dt><dd class="text-slate-800">{{ $company->phone }}</dd></div>
                @endif
                @if($company->address)
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Morada</dt><dd class="text-slate-800">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}{{ $company->state ? ' — '.$company->state : '' }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Colaboradores ativos</h2>
            <p class="text-2xl font-bold text-slate-800">{{ $company->active_employees_count }}</p>
            <a href="{{ route('painel.employees.index', ['q' => '', 'status' => 'active']) }}" class="text-xs text-indigo-600 hover:underline mt-2 inline-block">Gerir colaboradores no painel</a>
        </div>
    </div>

    <div class="bg-indigo-50 rounded-xl border border-indigo-100 p-6">
        <h2 class="text-sm font-semibold text-indigo-900 mb-3">Acesso ao app</h2>
        <p class="text-xs text-indigo-800/90 mb-4">Gestores desta empresa entram no aplicativo com o e-mail e a palavra-passe definidos na criação.</p>
        @if($gestores->isEmpty())
            <p class="text-sm text-slate-600">Nenhum gestor registado.</p>
        @else
            <ul class="space-y-3">
                @foreach($gestores as $g)
                    <li class="text-sm border border-indigo-100 rounded-lg bg-white px-3 py-2">
                        <p class="font-medium text-slate-800">{{ $g->name }}</p>
                        <p class="text-xs text-slate-600">{{ $g->email }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

@endsection
