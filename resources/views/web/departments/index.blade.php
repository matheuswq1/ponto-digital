@extends('web.layout')
@section('title', 'Departamentos')
@section('page-title', 'Departamentos')

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="flex flex-wrap items-center gap-3 mb-5">
    <form method="get" class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
        <input type="text" name="q" value="{{ $search }}" placeholder="Nome do departamento..."
               class="text-sm border border-slate-300 rounded-lg px-3 py-2 max-w-xs focus:ring-2 focus:ring-indigo-200 outline-none">
        @if(auth()->user()->isAdmin() && $companies->isNotEmpty())
        <select name="company_id" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
            <option value="">Todas as empresas</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected((string)$companyId === (string)$c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @endif
        <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
            <option value="active" @selected($status==='active')>Ativos</option>
            <option value="inactive" @selected($status==='inactive')>Inativos</option>
            <option value="all" @selected($status==='all')>Todos</option>
        </select>
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700">Filtrar</button>
    </form>
    <a href="{{ route('painel.departments.create') }}"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Novo departamento
    </a>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($departments->isEmpty())
        <div class="p-12 text-center text-slate-400 text-sm">Nenhum departamento encontrado.</div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    @if(auth()->user()->isAdmin())
                    <th class="px-4 py-3">Empresa</th>
                    @endif
                    <th class="px-4 py-3">Escala (entrada–saída)</th>
                    <th class="px-4 py-3">Colaboradores</th>
                    <th class="px-4 py-3 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($departments as $d)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $d->name }}</td>
                    @if(auth()->user()->isAdmin())
                    <td class="px-4 py-3 text-slate-600">{{ $d->company?->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-slate-600 font-mono text-xs">
                        @if($d->entry_time && $d->exit_time)
                            {{ \Carbon\Carbon::parse($d->entry_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($d->exit_time)->format('H:i') }}
                            @if($d->hasVariableLunchByDay())
                                <span class="text-indigo-600">· int. varia</span>
                            @else
                                ({{ $d->lunch_minutes }} min)
                            @endif
                        @else
                            <span class="text-amber-600">Configurar horários</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $d->employees_count }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('painel.departments.edit', $d) }}" class="text-indigo-600 hover:underline text-sm font-medium">Editar</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
        {{ $departments->links() }}
    </div>
    @endif
</div>
@endsection
