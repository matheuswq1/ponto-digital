@extends('web.layout')

@section('title', 'Auditoria')
@section('page-title', 'Auditoria')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Trilha de auditoria</h1>
        <p class="text-sm text-slate-500 mt-0.5">Registo de acções relevantes no painel</p>
    </div>
</div>

<form method="get" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Pesquisar</label>
            <input type="search" name="q" value="{{ $q }}"
                   placeholder="Descrição ou acção"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo de acção</label>
            <select name="action" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white">
                <option value="">Todas</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-lg">Filtrar</button>
            <a href="{{ route('painel.audit.index') }}" class="px-4 py-2 border border-slate-200 text-slate-600 text-sm rounded-lg hover:bg-slate-50">Limpar</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-600 uppercase">
                <tr>
                    <th class="px-4 py-2 text-left w-32">Data</th>
                    <th class="px-4 py-2 text-left">Utilizador</th>
                    <th class="px-4 py-2 text-left">Acção</th>
                    <th class="px-4 py-2 text-left">Assunto</th>
                    <th class="px-4 py-2 text-left">Descrição</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-4 py-2 text-slate-500 whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2 text-slate-800">{{ $log->user?->name ?? '—' }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-indigo-700">{{ $log->action }}</td>
                    <td class="px-4 py-2 text-slate-600 text-xs">{{ $log->subjectLabel() }}</td>
                    <td class="px-4 py-2 text-slate-600 text-xs max-w-md">{{ $log->description ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">Nenhum registo encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
        {{ $logs->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
