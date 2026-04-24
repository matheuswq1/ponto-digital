@extends('web.layout')
@section('title', 'Banco de Horas — ' . ($employee->user->name ?? ''))
@section('page-title', 'Banco de Horas do Colaborador')

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

<div class="max-w-4xl space-y-6">

    {{-- Card saldo --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:col-span-1">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Saldo atual</p>
            <p class="text-4xl font-bold {{ $balanceMinutes >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $balanceFormatted }}
            </p>
            <p class="text-xs text-slate-400 mt-1">{{ $balanceMinutes >= 0 ? 'Crédito disponível' : 'Débito pendente' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Colaborador</p>
            <p class="text-sm font-semibold text-slate-800">{{ $employee->user->name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ $employee->cargo }} · {{ $employee->company->name }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Ajuste manual</p>
            <form method="post" action="{{ route('painel.hour-bank.adjust', $employee) }}" class="space-y-2">
                @csrf
                <div class="flex gap-2">
                    <input type="number" name="minutes" placeholder="Min (ex: 60 ou -30)" required
                           class="flex-1 text-xs border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-200 outline-none">
                    @include('web.components.date-input', [
                        'name'     => 'date',
                        'value'    => now()->toDateString(),
                        'required' => true,
                        'class'    => 'text-xs border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-200 outline-none',
                    ])
                </div>
                <input type="text" name="description" placeholder="Descrição do ajuste" required maxlength="200"
                       class="w-full text-xs border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-200 outline-none">
                <button type="submit" class="w-full text-xs bg-indigo-600 text-white py-1.5 rounded-lg hover:bg-indigo-700 transition font-medium">
                    Aplicar ajuste
                </button>
            </form>
        </div>
    </div>

    {{-- Histórico de transações --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Histórico de movimentações</h2>
            <span class="text-xs text-slate-400">últimas {{ $transactions->count() }} entradas</span>
        </div>

        @if($transactions->isEmpty())
        <div class="p-10 text-center text-sm text-slate-400">Nenhuma movimentação registrada.</div>
        @else
        <div class="divide-y divide-slate-100">
            @foreach($transactions as $tx)
            @php
                $isCredit = $tx->minutes > 0;
                $abs      = abs($tx->minutes);
                $sign     = $isCredit ? '+' : '-';
                $h        = intdiv($abs, 60);
                $m        = $abs % 60;
                $fmt      = sprintf('%s%02d:%02d', $sign, $h, $m);
            @endphp
            <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition">
                <div class="flex h-8 w-8 items-center justify-center rounded-full shrink-0
                            {{ $isCredit ? 'bg-emerald-100' : 'bg-rose-100' }}">
                    @if($isCredit)
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>
                    @else
                    <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-700">{{ $tx->description ?? $tx->getTypeLabel() }}</p>
                    <p class="text-xs text-slate-400">{{ $tx->reference_date->format('d/m/Y') }} · {{ $tx->getTypeLabel() }}</p>
                </div>
                <p class="text-sm font-bold {{ $isCredit ? 'text-emerald-600' : 'text-rose-600' }}">{{ $fmt }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Solicitações de folga --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Solicitações de folga</h2>
        </div>

        @if($requests->isEmpty())
        <div class="p-10 text-center text-sm text-slate-400">Nenhuma solicitação de folga.</div>
        @else
        <div class="divide-y divide-slate-100">
            @foreach($requests as $req)
            @php
                $colors = ['pendente'=>'bg-amber-100 text-amber-700','aprovado'=>'bg-emerald-100 text-emerald-700','rejeitado'=>'bg-rose-100 text-rose-700'];
            @endphp
            <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-700 font-medium">{{ $req->requested_date->format('d/m/Y') }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $req->justification }}</p>
                </div>
                <p class="text-sm font-semibold text-indigo-700 shrink-0">{{ $req->requested_hours }}</p>
                <span class="shrink-0 inline-flex text-xs font-medium px-2 py-0.5 rounded-full {{ $colors[$req->status] ?? 'bg-slate-100 text-slate-600' }}">
                    {{ $req->status_label }}
                </span>
            </div>
            @if($req->approval_notes)
            <div class="px-5 pb-3 -mt-1">
                <p class="text-xs text-slate-400 bg-slate-50 rounded-lg px-3 py-1.5 border border-slate-100">{{ $req->approval_notes }}</p>
            </div>
            @endif
            @endforeach
        </div>
        @endif
    </div>

    <div class="flex">
        <a href="{{ route('painel.hour-bank.index') }}" class="text-sm text-slate-500 hover:underline">← Voltar às solicitações</a>
    </div>
</div>

@endsection
