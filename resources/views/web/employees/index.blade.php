@extends('web.layout')
@section('title', 'Colaboradores')
@section('page-title', 'Colaboradores')

@section('content')

{{-- Alertas --}}
@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

@if(session('import_errors'))
<div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
    <p class="font-semibold mb-1">Erros na importação:</p>
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach(session('import_errors') as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Barra de ações --}}
<div class="flex flex-wrap items-center gap-3 mb-5">
    {{-- Busca --}}
    <form method="get" class="flex items-center gap-2 flex-1 min-w-0">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar nome, e-mail, CPF ou cargo..."
                   class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
        </div>
        <select name="status" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
            <option value="active"   @selected($status==='active')>Ativos</option>
            <option value="inactive" @selected($status==='inactive')>Inativos</option>
            <option value="all"      @selected($status==='all')>Todos</option>
        </select>
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
        @if($search || $status !== 'active')
            <a href="{{ route('painel.employees.index') }}" class="text-sm text-slate-500 hover:underline">Limpar</a>
        @endif
    </form>

    {{-- Acções --}}
    <div class="flex items-center gap-2 shrink-0">
        {{-- Importar sistema legado --}}
        <button type="button" onclick="document.getElementById('importLegacyModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 border border-amber-300 bg-amber-50 px-3 py-2 rounded-lg hover:bg-amber-100 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
            Importar Legado
        </button>
        {{-- Importar CSV --}}
        <button type="button" onclick="document.getElementById('importModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 border border-slate-300 bg-white px-3 py-2 rounded-lg hover:bg-slate-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
            Importar CSV
        </button>
        {{-- Exportar --}}
        <a href="{{ route('painel.employees.export', ['status' => $status, 'q' => $search]) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 border border-slate-300 bg-white px-3 py-2 rounded-lg hover:bg-slate-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Exportar CSV
        </a>
        {{-- Novo colaborador --}}
        <a href="{{ route('painel.employees.create') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo colaborador
        </a>
    </div>
</div>

{{-- Tabela --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($employees->isEmpty())
        <div class="p-12 text-center">
            <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
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
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Facial</th>
                    <th class="px-5 py-3 hidden xl:table-cell">Admissão</th>
                    <th class="px-5 py-3 text-right">Ações</th>
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
                                <div class="flex items-center gap-1.5">
                                    <p class="font-medium text-slate-800">{{ $emp->user->name ?? '—' }}</p>
                                    @if($emp->user?->access_pending)
                                        <span class="inline-flex items-center text-[10px] font-semibold text-amber-700 bg-amber-100 rounded-full px-1.5 py-0.5 leading-none">Acesso pendente</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400">
                                    @if($emp->user?->access_pending)
                                        <span class="italic">E-mail não definido</span>
                                    @else
                                        {{ $emp->user->email ?? '' }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 hidden sm:table-cell text-slate-600">{{ $emp->cargo }}</td>
                    <td class="px-5 py-3 hidden md:table-cell text-slate-500 text-xs">{{ $emp->company->name ?? '—' }}</td>
                    <td class="px-5 py-3 hidden lg:table-cell text-slate-500 text-xs">{{ $emp->weekly_hours }}h/sem</td>
                    <td class="px-5 py-3">
                        @if($emp->active)
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-100 rounded-full px-2.5 py-0.5">Ativo</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2.5 py-0.5">Inativo</span>
                        @endif
                    </td>
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
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <a href="{{ route('painel.employees.show', $emp) }}"
                               title="Ver detalhes"
                               class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            </a>
                            <a href="{{ route('painel.employees.edit', $emp) }}"
                               title="Editar"
                               class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                            </a>
                            <form method="post" action="{{ route('painel.employees.toggle', $emp) }}" class="inline">
                                @csrf
                                <button type="submit" title="{{ $emp->active ? 'Desativar' : 'Reativar' }}"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-{{ $emp->active ? 'rose' : 'emerald' }}-600 hover:bg-{{ $emp->active ? 'rose' : 'emerald' }}-50 transition">
                                    @if($emp->active)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    @endif
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

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

{{-- Modal de importação legada --}}
<div id="importLegacyModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-slate-800 text-base">Importar do sistema legado</h3>
                <p class="text-xs text-slate-500 mt-0.5">Formato: pis;nome;administrador;matricula;rfid;...</p>
            </div>
            <button onclick="document.getElementById('importLegacyModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-4 text-xs text-amber-800">
            <p class="font-semibold mb-1">ℹ️ O que acontece na importação:</p>
            <ul class="list-disc pl-4 space-y-0.5">
                <li>Colaboradores são criados com base no <strong>PIS</strong> e <strong>Nome</strong></li>
                <li>O <strong>acesso ao app fica bloqueado</strong> até o admin definir e-mail e senha</li>
                <li>Registros com PIS já existente são ignorados</li>
                <li>Administradores do sistema legado são ignorados</li>
            </ul>
        </div>

        <form method="post" action="{{ route('painel.employees.import.legacy') }}" enctype="multipart/form-data">
            @csrf
            <div class="space-y-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600 mb-1 block">Empresa dos colaboradores</span>
                    <select name="company_id" required
                            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
                        <option value="">Selecione a empresa…</option>
                        @foreach(\App\Models\Company::where('active', true)->orderBy('name')->get() as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600 mb-1 block">Arquivo do sistema legado (.txt ou .csv)</span>
                    <input type="file" name="file" accept=".csv,.txt" required
                           class="block w-full text-sm text-slate-500 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none file:mr-3 file:py-2 file:px-4 file:border-0 file:bg-amber-50 file:text-amber-700 file:text-sm file:font-medium">
                </label>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="document.getElementById('importLegacyModal').classList.add('hidden')"
                        class="text-sm px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 transition">Cancelar</button>
                <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-amber-600 text-white font-medium hover:bg-amber-700 transition">Importar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal de importação --}}
<div id="importModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800 text-base">Importar colaboradores via CSV</h3>
            <button onclick="document.getElementById('importModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p class="text-sm text-slate-500 mb-4">
            O arquivo deve seguir o formato do template.
            <a href="{{ route('painel.employees.import.template') }}" class="text-indigo-600 hover:underline font-medium">Baixar template CSV</a>
        </p>
        <form method="post" action="{{ route('painel.employees.import') }}" enctype="multipart/form-data">
            @csrf
            <label class="block mb-3">
                <span class="text-xs font-medium text-slate-600 mb-1 block">Arquivo CSV (separado por ponto e vírgula)</span>
                <input type="file" name="file" accept=".csv,.txt" required
                       class="block w-full text-sm text-slate-500 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none file:mr-3 file:py-2 file:px-4 file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-sm file:font-medium">
            </label>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')"
                        class="text-sm px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 transition">Cancelar</button>
                <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition">Importar</button>
            </div>
        </form>
    </div>
</div>

@endsection
