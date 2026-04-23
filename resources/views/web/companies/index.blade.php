@extends('web.layout')
@section('title', 'Empresas')
@section('page-title', 'Empresas')

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="flex flex-wrap items-center gap-3 mb-5">
    <form method="get" class="flex items-center gap-2 flex-1 min-w-0">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nome, CNPJ ou e-mail..."
                   class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
        </div>
        <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
            <option value="active"   @selected($status==='active')>Ativas</option>
            <option value="inactive" @selected($status==='inactive')>Inativas</option>
            <option value="all"      @selected($status==='all')>Todas</option>
        </select>
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
        @if($search || $status !== 'active')
            <a href="{{ route('painel.companies.index') }}" class="text-sm text-slate-500 hover:underline">Limpar</a>
        @endif
    </form>

    <a href="{{ route('painel.companies.create') }}"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Nova empresa
    </a>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($companies->isEmpty())
        <div class="p-12 text-center text-slate-400 text-sm">Nenhuma empresa encontrada.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">CNPJ</th>
                        <th class="px-4 py-3">Colaboradores</th>
                        <th class="px-4 py-3">Gestores</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($companies as $c)
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $c->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->cnpj }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->active_employees_count }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->gestores_count }}</td>
                        <td class="px-4 py-3">
                            @if($c->active)
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Ativa</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inativa</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('painel.companies.show', $c) }}" class="text-indigo-600 hover:underline font-medium">Ver</a>
                                <a href="{{ route('painel.companies.edit', $c) }}" class="text-slate-500 hover:text-indigo-600 hover:underline text-sm">Editar</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/50">
            {{ $companies->links() }}
        </div>
    @endif
</div>

@endsection
