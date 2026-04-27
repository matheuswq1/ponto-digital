@extends('web.layout')

@section('title', 'Feriados')

@section('content')
@php
$scopeLabels = [
    'national'  => ['label' => 'Nacional',   'color' => 'bg-blue-100 text-blue-700'],
    'state'     => ['label' => 'Estadual',   'color' => 'bg-amber-100 text-amber-700'],
    'municipal' => ['label' => 'Municipal',  'color' => 'bg-violet-100 text-violet-700'],
    'custom'    => ['label' => 'Personalizado', 'color' => 'bg-emerald-100 text-emerald-700'],
];
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Feriados</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gerencie feriados nacionais, estaduais, municipais e personalizados</p>
    </div>
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Coluna esquerda: filtros + cadastro ── --}}
    <div class="space-y-4">

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <h2 class="text-sm font-bold text-slate-700 mb-3">Filtrar</h2>
            <form method="get">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Ano</label>
                        <input type="number" name="year" value="{{ $year }}" min="2020" max="2035"
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Empresa</label>
                        <select name="company_id" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="">Nacionais / sem empresa</option>
                            @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo</label>
                        <select name="scope" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="">Todos</option>
                            @foreach($scopeLabels as $k => $sl)
                            <option value="{{ $k }}" @selected($scope === $k)>{{ $sl['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg transition">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        {{-- Sincronizar via API --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <h2 class="text-sm font-bold text-slate-700 mb-1">Sincronizar via API</h2>
            <p class="text-xs text-slate-500 mb-3">Importa feriados nacionais, estaduais e municipais (BrasilAPI + GitHub).</p>
            <form method="post" action="{{ route('painel.holidays.sync') }}">
                @csrf
                <div class="space-y-2">
                    <input type="number" name="year" value="{{ $year }}" min="2020" max="2035"
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400"
                        placeholder="Ano">
                    <select name="company_id" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">Todas as empresas</option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition">
                        🔄 Sincronizar agora
                    </button>
                </div>
            </form>
        </div>

        {{-- Adicionar feriado --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4" x-data="{ open: false }">
            <button @click="open = !open"
                class="w-full flex items-center justify-between text-sm font-bold text-slate-700">
                <span>+ Adicionar feriado</span>
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div x-show="open" x-transition class="mt-3">
                <form method="post" action="{{ route('painel.holidays.store') }}" class="space-y-2">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nome *</label>
                        <input type="text" name="name" required maxlength="120"
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400"
                            placeholder="Ex: Aniversário da cidade">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Data *</label>
                        @include('web.components.date-input', ['name' => 'date', 'value' => ''])
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo *</label>
                        <select name="scope" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                            @foreach($scopeLabels as $k => $sl)
                            <option value="{{ $k }}">{{ $sl['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Estado (UF)</label>
                        <input type="text" name="state" maxlength="2"
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400"
                            placeholder="SP">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Município</label>
                        <input type="text" name="city" maxlength="120"
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400"
                            placeholder="São Paulo">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Empresa</label>
                        <select name="company_id" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="">Nenhuma (nacional)</option>
                            @foreach($companies as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="recurring" value="1" class="rounded accent-brand-600">
                        Recorrente (repete todo ano)
                    </label>
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition">
                        Salvar feriado
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Coluna direita: lista ── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <p class="text-sm font-bold text-slate-700">
                    Feriados {{ $year }}
                    @if($companyId) — {{ $companies->find($companyId)?->name }} @else — Nacionais @endif
                </p>
                <span class="text-xs text-slate-400">{{ $holidays->total() }} registros</span>
            </div>

            @if($holidays->isEmpty())
            <div class="p-10 text-center text-slate-400 text-sm">
                Nenhum feriado encontrado. Use o botão "Sincronizar via API" ou adicione manualmente.
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 font-semibold">
                            <th class="px-4 py-2 text-left">Data</th>
                            <th class="px-4 py-2 text-left">Nome</th>
                            <th class="px-3 py-2 text-center">Tipo</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                            <th class="px-3 py-2 text-center">Recorrente</th>
                            <th class="px-3 py-2 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($holidays as $holiday)
                        <tr class="hover:bg-slate-50 transition-colors" x-data="{ editing: false }">
                            {{-- View mode --}}
                            <template x-if="!editing">
                                <td class="px-4 py-2.5 font-mono text-slate-700 text-xs whitespace-nowrap">
                                    {{ $holiday->date->format('d/m/Y') }}
                                    @if($holiday->recurring)
                                    <span class="ml-1 text-[10px] text-amber-500" title="Recorrente">↻</span>
                                    @endif
                                </td>
                            </template>
                            <template x-if="editing">
                                <td class="px-2 py-1.5">
                                    <form id="edit-{{ $holiday->id }}" method="post"
                                          action="{{ route('painel.holidays.update', $holiday) }}">
                                        @csrf @method('PUT')
                                        @include('web.components.date-input', ['name' => 'date', 'value' => $holiday->date->format('Y-m-d'), 'class' => 'text-xs'])
                                    </form>
                                </td>
                            </template>

                            @if(!isset($edit_id) || $edit_id != $holiday->id)
                            <td class="px-4 py-2.5 text-slate-800" x-show="!editing">{{ $holiday->name }}</td>
                            <td class="px-3 py-2.5 text-center" x-show="!editing">
                                @php $sl = $scopeLabels[$holiday->scope] ?? ['label' => $holiday->scope, 'color' => 'bg-slate-100 text-slate-600']; @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $sl['color'] }}">{{ $sl['label'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs text-slate-500" x-show="!editing">{{ $holiday->state ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-center" x-show="!editing">
                                @if($holiday->recurring)
                                <span class="text-amber-500 font-bold text-xs">Sim</span>
                                @else
                                <span class="text-slate-300 text-xs">Não</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center" x-show="!editing">
                                <button @click="editing = true"
                                    class="text-xs text-brand-600 hover:text-brand-800 font-semibold mr-2">
                                    Editar
                                </button>
                                <form method="post" action="{{ route('painel.holidays.destroy', $holiday) }}"
                                      class="inline" onsubmit="return confirm('Remover este feriado?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-500 hover:text-rose-700 font-semibold">
                                        Remover
                                    </button>
                                </form>
                            </td>
                            @endif

                            {{-- Edit inline --}}
                            <td colspan="4" class="px-2 py-1.5" x-show="editing">
                                <div class="flex gap-2 flex-wrap items-center">
                                    <input type="text" name="name" form="edit-{{ $holiday->id }}"
                                        value="{{ $holiday->name }}"
                                        class="text-sm border border-slate-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-brand-400 w-40">
                                    <select name="scope" form="edit-{{ $holiday->id }}"
                                        class="text-sm border border-slate-300 rounded px-2 py-1 bg-white focus:outline-none">
                                        @foreach($scopeLabels as $k => $sl)
                                        <option value="{{ $k }}" @selected($holiday->scope === $k)>{{ $sl['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="state" form="edit-{{ $holiday->id }}"
                                        value="{{ $holiday->state }}" maxlength="2"
                                        class="text-sm border border-slate-300 rounded px-2 py-1 w-12 focus:outline-none"
                                        placeholder="UF">
                                    <label class="flex items-center gap-1 text-xs text-slate-600">
                                        <input type="checkbox" name="recurring" value="1" form="edit-{{ $holiday->id }}"
                                            {{ $holiday->recurring ? 'checked' : '' }} class="accent-brand-600">
                                        Recorrente
                                    </label>
                                </div>
                            </td>
                            <td class="px-3 py-1.5 text-center" x-show="editing">
                                <button type="submit" form="edit-{{ $holiday->id }}"
                                    class="text-xs text-emerald-600 hover:text-emerald-800 font-semibold mr-2">
                                    Salvar
                                </button>
                                <button type="button" @click="editing = false"
                                    class="text-xs text-slate-400 hover:text-slate-600 font-semibold">
                                    Cancelar
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Paginação --}}
            @if($holidays->hasPages())
            <div class="px-4 py-3 border-t border-slate-200">
                {{ $holidays->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>

</div>

@endsection
