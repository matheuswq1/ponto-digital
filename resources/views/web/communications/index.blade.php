@extends('web.layout')
@section('title', 'Comunicados')
@section('page-title', 'Comunicados')

@section('content')
@if(session('success'))
<div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-lg font-bold text-slate-800">Comunicados</h1>
        <p class="text-sm text-slate-400 mt-0.5">Mural de avisos para os colaboradores</p>
    </div>
    <a href="{{ route('painel.communications.create') }}"
       class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Novo comunicado
    </a>
</div>

{{-- Filtros --}}
<form method="get" class="flex flex-wrap gap-3 mb-5">
    <select name="type" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
        <option value="">Todos os tipos</option>
        <option value="info"    @selected(request('type')=='info')>Informativo</option>
        <option value="aviso"   @selected(request('type')=='aviso')>Aviso</option>
        <option value="urgente" @selected(request('type')=='urgente')>Urgente</option>
    </select>
    <button class="bg-slate-700 text-white text-sm px-4 py-2 rounded-lg hover:bg-slate-800">Filtrar</button>
    <a href="{{ route('painel.communications.index') }}" class="text-sm text-slate-500 py-2 hover:text-slate-700">Limpar</a>
</form>

{{-- Lista --}}
@if($communications->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 bg-white rounded-xl border border-slate-200 text-slate-400">
        <svg class="w-12 h-12 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46"/>
        </svg>
        <p class="font-medium text-sm">Nenhum comunicado encontrado</p>
    </div>
@else
    <div class="space-y-3">
        @foreach($communications as $c)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 {{ $c->pinned ? 'border-l-4 border-l-indigo-500' : '' }}">
            <div class="flex items-start gap-4">
                {{-- Ícone tipo --}}
                @php
                    $iconBg = match($c->type) { 'urgente'=>'bg-rose-100','aviso'=>'bg-amber-100', default=>'bg-blue-100' };
                    $iconColor = match($c->type) { 'urgente'=>'text-rose-600','aviso'=>'text-amber-600', default=>'text-blue-600' };
                @endphp
                <div class="shrink-0 w-9 h-9 rounded-lg {{ $iconBg }} flex items-center justify-center">
                    @if($c->type === 'urgente')
                        <svg class="w-4 h-4 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    @elseif($c->type === 'aviso')
                        <svg class="w-4 h-4 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                    @else
                        <svg class="w-4 h-4 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center flex-wrap gap-2 mb-1">
                        @if($c->pinned)
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2Z"/></svg>
                        @endif
                        <span class="font-semibold text-slate-800 text-sm">{{ $c->title }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full border font-medium {{ $c->getTypeBadgeClass() }}">{{ $c->getTypeLabel() }}</span>
                        @if(!$c->isPublished())
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200 font-medium">Rascunho</span>
                        @endif
                        @if($c->isExpired())
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-400 border border-slate-200 font-medium">Expirado</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500 line-clamp-2">{{ $c->body }}</p>
                    <div class="flex items-center gap-3 mt-2 text-xs text-slate-400">
                        <span>Por {{ $c->author?->name ?? 'N/A' }}</span>
                        @if($c->published_at)
                            <span>· {{ $c->published_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($c->expires_at)
                            <span>· Expira {{ $c->expires_at->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <a href="{{ route('painel.communications.edit', $c) }}"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                    </a>
                    <form method="post" action="{{ route('painel.communications.destroy', $c) }}"
                          onsubmit="return confirm('Remover comunicado?')">
                        @csrf @method('DELETE')
                        <button class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @if($communications->hasPages())
    <div class="mt-4">{{ $communications->links() }}</div>
    @endif
@endif
@endsection
