@extends('web.layout')
@section('title', 'Férias')
@section('page-title', 'Férias')

@section('content')
@if(session('success'))
<div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-800">Solicitações de Férias</h1>
        <p class="text-sm text-slate-400 mt-0.5">Gerencie as solicitações enviadas pelos colaboradores</p>
    </div>
</div>

{{-- Filtros --}}
<form method="get" class="flex flex-wrap gap-3 mb-5">
    <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
        <option value="">Todos os status</option>
        <option value="pendente"  @selected(request('status')=='pendente')>Pendente</option>
        <option value="aprovado"  @selected(request('status')=='aprovado')>Aprovado</option>
        <option value="rejeitado" @selected(request('status')=='rejeitado')>Rejeitado</option>
    </select>
    <button class="bg-slate-700 text-white text-sm px-4 py-2 rounded-lg hover:bg-slate-800">Filtrar</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($requests->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
            <svg class="w-12 h-12 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
            </svg>
            <p class="text-sm font-medium">Nenhuma solicitação encontrada</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Colaborador</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Período</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Dias</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Solicitado em</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($requests as $r)
                <tr class="hover:bg-slate-50 transition-colors" x-data="{ open: false }">
                    <td class="px-5 py-3.5">
                        <div class="font-medium text-slate-800">{{ $r->employee->user?->name ?? 'N/A' }}</div>
                        <div class="text-xs text-slate-400">{{ $r->employee->cargo ?? '' }}</div>
                    </td>
                    <td class="px-5 py-3.5 text-slate-600 hidden sm:table-cell">
                        {{ $r->start_date->format('d/m/Y') }} → {{ $r->end_date->format('d/m/Y') }}
                    </td>
                    <td class="px-5 py-3.5 text-slate-600 font-semibold hidden md:table-cell">
                        {{ $r->days }} dias
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $r->getStatusBadgeClass() }}">
                            {{ $r->getStatusLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs hidden lg:table-cell">
                        {{ $r->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-5 py-3.5">
                        @if($r->status === 'pendente')
                        <div class="flex items-center gap-2">
                            <form method="post" action="{{ route('painel.vacation-requests.approve', $r) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    Aprovar
                                </button>
                            </form>
                            <form method="post" action="{{ route('painel.vacation-requests.reject', $r) }}"
                                  onsubmit="return confirm('Rejeitar esta solicitação?')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg hover:bg-rose-100 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    Rejeitar
                                </button>
                            </form>
                        </div>
                        @else
                            <div class="text-xs text-slate-400">
                                @if($r->reviewer) Por {{ $r->reviewer->name }}<br>{{ $r->reviewed_at?->format('d/m/Y') }} @endif
                                @if($r->review_notes) <span class="italic">— {{ $r->review_notes }}</span> @endif
                            </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($requests->hasPages())
        <div class="px-5 py-3 border-t border-slate-100">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
@endsection
