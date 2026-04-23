@extends('web.layout')
@section('title', 'Colaboradores')
@section('page-title', 'Colaboradores')

@section('content')

{{-- Filtro --}}
<form method="get" class="flex items-center gap-3 mb-6">
    <div class="relative flex-1 max-w-sm">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input type="text" name="q" value="{{ $search }}" placeholder="Buscar nome, e-mail ou cargo..."
               class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
    </div>
    <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
    @if($search)
        <a href="{{ route('painel.employees.index') }}" class="text-sm text-slate-500 hover:underline">Limpar</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($employees->isEmpty())
        <div class="p-12 text-center">
            <p class="text-slate-400 text-sm">Nenhum colaborador encontrado{{ $search ? ' para "' . e($search) . '"' : '' }}.</p>
        </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-slate-200">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50">
                    <th class="px-5 py-3">Colaborador</th>
                    <th class="px-5 py-3 hidden sm:table-cell">Cargo</th>
                    <th class="px-5 py-3 hidden md:table-cell">Empresa</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Jornada</th>
                    <th class="px-5 py-3">Facial</th>
                    <th class="px-5 py-3 hidden xl:table-cell">Admissão</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($employees as $emp)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                                {{ strtoupper(substr($emp->user->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-slate-800">{{ $emp->user->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $emp->user->email ?? '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 hidden sm:table-cell text-slate-600">{{ $emp->cargo }}</td>
                    <td class="px-5 py-3 hidden md:table-cell text-slate-500 text-xs">{{ $emp->company->name ?? '—' }}</td>
                    <td class="px-5 py-3 hidden lg:table-cell text-slate-500 text-xs">{{ $emp->weekly_hours }}h/sem</td>
                    <td class="px-5 py-3">
                        @if($emp->face_enrolled)
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-100 rounded-full px-2.5 py-0.5">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                Cadastrado
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2.5 py-0.5">Pendente</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden xl:table-cell text-slate-500 text-xs">
                        {{ $emp->admission_date?->format('d/m/Y') ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Rodapé paginação --}}
    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100 text-xs text-slate-500">
        <span>{{ $employees->firstItem() }}–{{ $employees->lastItem() }} de {{ $employees->total() }} colaboradores</span>
        @if($employees->hasPages())
        <nav class="inline-flex -space-x-px rounded overflow-hidden border border-slate-200 text-xs">
            @if($employees->onFirstPage())
                <span class="px-3 py-1.5 bg-white text-slate-300 border-r border-slate-200">‹</span>
            @else
                <a href="{{ $employees->previousPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">‹</a>
            @endif
            @foreach($employees->getUrlRange(max(1,$employees->currentPage()-2), min($employees->lastPage(),$employees->currentPage()+2)) as $page => $url)
                @if($page == $employees->currentPage())
                    <span class="px-3 py-1.5 bg-indigo-600 text-white border-r border-indigo-600 font-semibold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">{{ $page }}</a>
                @endif
            @endforeach
            @if($employees->hasMorePages())
                <a href="{{ $employees->nextPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-600">›</a>
            @else
                <span class="px-3 py-1.5 bg-white text-slate-300">›</span>
            @endif
        </nav>
        @endif
    </div>
    @endif
</div>

@endsection
