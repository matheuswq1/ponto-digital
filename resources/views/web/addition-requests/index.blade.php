@extends('web.layout')
@section('title', 'Solicitações de adição de ponto')
@section('page-title', 'Adições de ponto pendentes')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">Pedidos de colaboradores para adicionar um ponto esquecido. Após aprovação, o ponto é criado e o banco de horas recalculado.</p>
    @if($additions->total() > 0)
    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1 shrink-0">
        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
        {{ $additions->total() }} pendente{{ $additions->total() > 1 ? 's' : '' }}
    </span>
    @endif
</div>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
@endif

@if($additions->isEmpty())
    <div class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-sm">
        <svg class="mx-auto w-12 h-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <h3 class="text-lg font-semibold text-slate-700 mb-1">Tudo em dia!</h3>
        <p class="text-sm text-slate-400">Nenhuma solicitação de adição de ponto pendente.</p>
    </div>
@else
<div class="space-y-4">
    @foreach($additions as $add)
    @php
        $name    = $add->employee->user->name ?? '—';
        $initial = strtoupper(substr($name, 0, 1));
        $typeLabels = ['entrada' => 'Entrada', 'saida' => 'Saída'];
        $typeColors = ['entrada' => 'emerald', 'saida' => 'rose'];
        $tLabel = $typeLabels[$add->type] ?? $add->type;
        $tColor = $typeColors[$add->type] ?? 'slate';
        $created = \Carbon\Carbon::parse($add->created_at)->setTimezone(config('app.timezone', 'America/Sao_Paulo'));
    @endphp
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100 bg-slate-50">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm shrink-0">{{ $initial }}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800">{{ $name }}</p>
                <p class="text-xs text-slate-400">Solicitado {{ $created->locale('pt_BR')->diffForHumans() }} · {{ $created->format('d/m/Y H:i') }}</p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold px-2.5 py-0.5">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Pendente
            </span>
        </div>

        {{-- Corpo --}}
        <div class="grid sm:grid-cols-2 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
            <div class="px-5 py-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Ponto a adicionar</p>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-{{ $tColor }}-100 text-{{ $tColor }}-700 mb-2">{{ $tLabel }}</span>
                <p class="text-sm text-slate-500">{{ $add->datetime_local?->format('d/m/Y') }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-0.5">{{ $add->datetime_local?->format('H:i') }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Justificativa</p>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $add->justification }}</p>
            </div>
        </div>

        {{-- Ações --}}
        <div class="flex flex-col sm:flex-row gap-3 px-5 py-4 bg-slate-50 border-t border-slate-100">
            <form method="post" action="{{ route('painel.addition-requests.approve', $add) }}" class="flex-1 flex gap-2">
                @csrf
                <input type="text" name="notes" placeholder="Nota interna (opcional)"
                    class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-200 outline-none">
                <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    Aprovar e adicionar
                </button>
            </form>
            <form method="post" action="{{ route('painel.addition-requests.reject', $add) }}" class="flex-1 flex gap-2">
                @csrf
                <input type="text" name="notes" required placeholder="Motivo da rejeição (obrigatório)"
                    class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-rose-200 outline-none">
                <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    Rejeitar
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>

@if($additions->hasPages())
<div class="mt-6 flex justify-center">
    <nav class="inline-flex -space-x-px rounded-lg overflow-hidden border border-slate-200 shadow-sm text-sm">
        @if($additions->onFirstPage())
            <span class="px-3 py-2 text-slate-300 bg-white border-r border-slate-200 cursor-not-allowed">‹</span>
        @else
            <a href="{{ $additions->previousPageUrl() }}" class="px-3 py-2 text-slate-600 bg-white hover:bg-slate-50 border-r border-slate-200">‹</a>
        @endif
        @foreach($additions->getUrlRange(1, $additions->lastPage()) as $page => $url)
            @if($page == $additions->currentPage())
                <span class="px-3 py-2 bg-indigo-600 text-white border-r border-indigo-600 font-medium">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="px-3 py-2 text-slate-600 bg-white hover:bg-slate-50 border-r border-slate-200">{{ $page }}</a>
            @endif
        @endforeach
        @if($additions->hasMorePages())
            <a href="{{ $additions->nextPageUrl() }}" class="px-3 py-2 text-slate-600 bg-white hover:bg-slate-50">›</a>
        @else
            <span class="px-3 py-2 text-slate-300 bg-white cursor-not-allowed">›</span>
        @endif
    </nav>
</div>
@endif
@endif

@endsection
