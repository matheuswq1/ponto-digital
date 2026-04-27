@extends('web.layout')
@section('title', 'Utilizadores')
@section('page-title', 'Utilizadores')

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    {{ session('error') }}
</div>
@endif

{{-- Barra de ações --}}
<div class="flex flex-wrap items-center gap-3 mb-5">
    <form method="get" class="flex items-center gap-2 flex-1 min-w-0">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar nome ou e-mail..."
                   class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white">
        </div>
        <select name="role" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 outline-none">
            <option value="">Todos</option>
            <option value="admin"  @selected($role==='admin')>Administrador</option>
            <option value="gestor" @selected($role==='gestor')>Gestor de RH</option>
        </select>
        <button type="submit" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">Filtrar</button>
    </form>
    <a href="{{ route('painel.users.create') }}"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Novo utilizador
    </a>
</div>

{{-- Tabela --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    @if($users->isEmpty())
        <div class="p-12 text-center text-slate-400 text-sm">Nenhum utilizador encontrado.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b border-slate-200">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50">
                    <th class="px-5 py-3">Utilizador</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3 hidden md:table-cell">Estado</th>
                    <th class="px-5 py-3 hidden lg:table-cell">Criado em</th>
                    <th class="px-5 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $user)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                                        {{ $user->isAdmin() ? 'bg-amber-100 text-amber-700' : ($user->isGestor() ? 'bg-sky-100 text-sky-700' : 'bg-indigo-100 text-indigo-700') }}
                                        text-xs font-bold">
                                {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-slate-800">{{ $user->name }}</p>
                                <p class="text-xs text-slate-400">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        @php
                            $roleColor = match($user->role) {
                                'admin' => 'amber', 'gestor' => 'sky', default => 'slate'
                            };
                            $roleLabel = match($user->role) {
                                'admin' => 'Administrador', 'gestor' => 'Gestor', 'funcionario' => 'Colaborador', default => ucfirst($user->role)
                            };
                        @endphp
                        <span class="inline-flex items-center text-xs font-medium text-{{ $roleColor }}-700 bg-{{ $roleColor }}-100 rounded-full px-2.5 py-0.5">
                            {{ $roleLabel }}
                        </span>
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell">
                        @if($user->active ?? true)
                            <span class="inline-flex items-center text-xs font-medium text-emerald-700 bg-emerald-100 rounded-full px-2.5 py-0.5">Ativo</span>
                        @else
                            <span class="inline-flex items-center text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2.5 py-0.5">Inativo</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden lg:table-cell text-slate-500 text-xs">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('painel.users.edit', $user) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700 border border-indigo-200 rounded-lg px-2.5 py-1.5 hover:bg-indigo-50 transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                Editar
                            </a>
                            @if($user->id !== auth()->id())
                            <button type="button"
                                    onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:text-rose-700 border border-rose-200 rounded-lg px-2.5 py-1.5 hover:bg-rose-50 transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                Excluir
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100 text-xs text-slate-500">
        <span>{{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }} utilizadores</span>
        @if($users->hasPages())
        <nav class="inline-flex -space-x-px rounded overflow-hidden border border-slate-200 text-xs">
            @if($users->onFirstPage())
                <span class="px-3 py-1.5 bg-white text-slate-300 border-r border-slate-200">‹</span>
            @else
                <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">‹</a>
            @endif
            @foreach($users->getUrlRange(max(1,$users->currentPage()-2), min($users->lastPage(),$users->currentPage()+2)) as $page => $url)
                @if($page == $users->currentPage())
                    <span class="px-3 py-1.5 bg-indigo-600 text-white border-r border-indigo-600 font-semibold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 border-r border-slate-200 text-slate-600">{{ $page }}</a>
                @endif
            @endforeach
            @if($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-600">›</a>
            @else
                <span class="px-3 py-1.5 bg-white text-slate-300">›</span>
            @endif
        </nav>
        @endif
    </div>
    @endif
</div>

{{-- Modal de confirmação de exclusão --}}
<div id="modal-delete" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Excluir utilizador</h3>
                <p class="text-xs text-slate-500 mt-0.5">Esta ação não pode ser desfeita.</p>
            </div>
        </div>
        <p class="text-sm text-slate-600 mb-5">
            Tem certeza que deseja excluir o utilizador <strong id="modal-user-name" class="text-slate-800"></strong>?
        </p>
        <div class="flex gap-3">
            <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg px-4 py-2.5 transition">
                Cancelar
            </button>
            <form id="form-delete" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg px-4 py-2.5 transition">
                    Sim, excluir
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, userName) {
    document.getElementById('modal-user-name').textContent = userName;
    document.getElementById('form-delete').action = '/painel/utilizadores/' + userId;
    document.getElementById('modal-delete').classList.remove('hidden');
    document.getElementById('modal-delete').classList.add('flex');
}
function closeDeleteModal() {
    document.getElementById('modal-delete').classList.add('hidden');
    document.getElementById('modal-delete').classList.remove('flex');
}
document.getElementById('modal-delete').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

@endsection
