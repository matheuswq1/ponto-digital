@extends('web.layout')
@section('title', 'Banco de Horas')
@section('page-title', 'Banco de Horas')

@section('content')

{{-- Cabeçalho --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-sm text-slate-500">Gerencie as solicitações de folga compensatória dos colaboradores.</p>
    </div>
    @if($requests->total() > 0 && $status === 'pendente')
    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1">
        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
        {{ $requests->total() }} pendente{{ $requests->total() > 1 ? 's' : '' }}
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
<div class="mb-4 flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- Filtros --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3 mb-5 flex flex-wrap items-center gap-3">
    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Filtrar:</span>

    @foreach(['pendente'=>'Pendentes','aprovado'=>'Aprovadas','rejeitado'=>'Rejeitadas','todos'=>'Todas'] as $val => $lbl)
    <a href="{{ request()->fullUrlWithQuery(['status' => $val, 'page' => 1]) }}"
       class="text-xs font-medium px-3 py-1.5 rounded-lg border transition
              {{ $status === $val
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
        {{ $lbl }}
    </a>
    @endforeach

    @if(count($companies) > 1)
    <form method="get" class="ml-auto flex items-center gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <select name="company_id" onchange="this.form.submit()"
                class="text-xs border border-slate-300 rounded-lg px-2 py-1.5 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
            <option value="">Todas as empresas</option>
            @foreach($companies as $co)
                <option value="{{ $co->id }}" @selected($companyId == $co->id)>{{ $co->name }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($requests->isEmpty())
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-sm">
    <svg class="mx-auto w-12 h-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
    </svg>
    <h3 class="text-lg font-semibold text-slate-700 mb-1">Nenhuma solicitação</h3>
    <p class="text-sm text-slate-400">Não há solicitações com o filtro selecionado.</p>
</div>
@else
<div class="space-y-4">
    @foreach($requests as $req)
    @php
        $name    = $req->employee->user->name ?? '—';
        $initial = strtoupper(substr($name, 0, 1));
        $balance = $req->employee->hour_bank_balance_minutes;
        $balFmt  = $req->employee->hour_bank_balance_formatted;
        $statusColors = [
            'pendente'  => 'bg-amber-100 text-amber-700',
            'aprovado'  => 'bg-emerald-100 text-emerald-700',
            'rejeitado' => 'bg-rose-100 text-rose-700',
        ];
        $dotColors = [
            'pendente'  => 'bg-amber-500',
            'aprovado'  => 'bg-emerald-500',
            'rejeitado' => 'bg-rose-500',
        ];
    @endphp
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="req-{{ $req->id }}">

        {{-- Header --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100 bg-slate-50">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm shrink-0">
                {{ $initial }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800">{{ $name }}</p>
                <p class="text-xs text-slate-400">{{ $req->employee->company->name ?? '—' }} · Solicitado {{ $req->created_at->locale('pt_BR')->diffForHumans() }}</p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1 rounded-full text-xs font-semibold px-2.5 py-0.5 {{ $statusColors[$req->status] ?? 'bg-slate-100 text-slate-700' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $dotColors[$req->status] ?? 'bg-slate-400' }}"></span>
                {{ $req->status_label }}
            </span>
        </div>

        {{-- Corpo --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 px-5 py-4">
            <div>
                <p class="text-xs text-slate-400 mb-0.5">Data da folga</p>
                <p class="text-sm font-semibold text-slate-800">{{ $req->requested_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 mb-0.5">Horas solicitadas</p>
                <p class="text-sm font-semibold text-indigo-700">{{ $req->requested_hours }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 mb-0.5">Saldo atual</p>
                <p class="text-sm font-semibold {{ $balance >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $balFmt }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 mb-0.5">Cargo</p>
                <p class="text-sm text-slate-700">{{ $req->employee->cargo ?? '—' }}</p>
            </div>
        </div>

        @if($req->justification)
        <div class="px-5 pb-4">
            <p class="text-xs text-slate-400 mb-1">Justificativa do colaborador</p>
            <p class="text-sm text-slate-700 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100">{{ $req->justification }}</p>
        </div>
        @endif

        @if($req->approval_notes)
        <div class="px-5 pb-4">
            <p class="text-xs text-slate-400 mb-1">Observação do gestor</p>
            <p class="text-sm text-slate-700 bg-{{ $req->isApproved() ? 'emerald' : 'rose' }}-50 rounded-lg px-3 py-2 border border-{{ $req->isApproved() ? 'emerald' : 'rose' }}-100">{{ $req->approval_notes }}</p>
        </div>
        @endif

        {{-- Ações (apenas se pendente) --}}
        @if($req->isPending())
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex flex-wrap gap-2">

            {{-- Botão aprovar --}}
            <button onclick="document.getElementById('approve-modal-{{ $req->id }}').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg
                           bg-emerald-600 text-white hover:bg-emerald-700 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                Aprovar
            </button>

            {{-- Botão rejeitar --}}
            <button onclick="document.getElementById('reject-modal-{{ $req->id }}').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg
                           border border-rose-300 text-rose-600 hover:bg-rose-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                Rejeitar
            </button>

            <a href="{{ route('painel.hour-bank.employee', $req->employee) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg
                      border border-slate-300 text-slate-600 hover:bg-slate-100 transition ml-auto">
                Ver saldo completo
            </a>
        </div>
        @endif
    </div>

    {{-- Modal Aprovar --}}
    <div id="approve-modal-{{ $req->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-semibold text-slate-800 mb-1">Aprovar solicitação</h3>
            <p class="text-sm text-slate-500 mb-4">Folga de <strong>{{ $req->requested_hours }}</strong> para <strong>{{ $req->requested_date->format('d/m/Y') }}</strong> — {{ $name }}</p>
            <form method="post" action="{{ route('painel.hour-bank.approve', $req) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Observação (opcional)</label>
                    <textarea name="notes" rows="3" maxlength="500"
                              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none resize-none"
                              placeholder="Comentário para o colaborador..."></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-emerald-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-emerald-700 transition">Confirmar aprovação</button>
                    <button type="button" onclick="document.getElementById('approve-modal-{{ $req->id }}').classList.add('hidden')"
                            class="flex-1 border border-slate-300 text-slate-600 text-sm font-medium py-2 rounded-lg hover:bg-slate-50 transition">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Rejeitar --}}
    <div id="reject-modal-{{ $req->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-base font-semibold text-slate-800 mb-1">Rejeitar solicitação</h3>
            <p class="text-sm text-slate-500 mb-4">Você está rejeitando a folga de <strong>{{ $req->requested_hours }}</strong> para <strong>{{ $req->requested_date->format('d/m/Y') }}</strong> — {{ $name }}</p>
            <form method="post" action="{{ route('painel.hour-bank.reject', $req) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Motivo da rejeição <span class="text-rose-500">*</span></label>
                    <textarea name="notes" rows="3" required minlength="5" maxlength="500"
                              class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-200 focus:border-rose-400 outline-none resize-none"
                              placeholder="Explique o motivo ao colaborador..."></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-rose-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-rose-700 transition">Confirmar rejeição</button>
                    <button type="button" onclick="document.getElementById('reject-modal-{{ $req->id }}').classList.add('hidden')"
                            class="flex-1 border border-slate-300 text-slate-600 text-sm font-medium py-2 rounded-lg hover:bg-slate-50 transition">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    @endforeach
</div>

{{-- Paginação --}}
@if($requests->hasPages())
<div class="mt-6">{{ $requests->links() }}</div>
@endif
@endif

@endsection
