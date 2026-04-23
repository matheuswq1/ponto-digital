@extends('web.layout')
@section('title', $company->name)
@section('page-title', $company->name)

@section('content')

@if(session('success'))
<div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    <div>
        <p>{{ session('success') }}</p>
        @if(session('gestor_password_plain'))
            <p class="mt-2 font-mono text-xs bg-white/60 rounded px-2 py-1 border border-emerald-200">
                Senha do gestor: <strong>{{ session('gestor_password_plain') }}</strong>
            </p>
            <p class="mt-1 text-xs text-emerald-800/90">Guarde esta senha — não será mostrada novamente.</p>
        @endif
        @if(session('totem_password_plain'))
            <p class="mt-2 font-mono text-xs bg-white/60 rounded px-2 py-1 border border-emerald-200">
                Senha do totem: <strong>{{ session('totem_password_plain') }}</strong>
            </p>
            <p class="mt-1 text-xs text-emerald-800/90">Guarde esta senha — não será mostrada novamente.</p>
        @endif
    </div>
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4 space-y-0.5">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="{{ route('painel.companies.index') }}" class="text-sm text-slate-600 hover:underline">← Empresas</a>
    <span class="text-slate-300">|</span>
    <a href="{{ route('painel.companies.edit', $company) }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline">Editar dados da empresa</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Coluna principal --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Identificação --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">Identificação</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-slate-500">CNPJ</dt><dd class="font-medium text-slate-800">{{ $company->cnpj }}</dd></div>
                <div><dt class="text-xs text-slate-500">Estado</dt><dd>
                    @if($company->active)
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Ativa</span>
                    @else
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inativa</span>
                    @endif
                </dd></div>
                @if($company->email)
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">E-mail</dt><dd class="text-slate-800">{{ $company->email }}</dd></div>
                @endif
                @if($company->phone)
                <div><dt class="text-xs text-slate-500">Telefone</dt><dd class="text-slate-800">{{ $company->phone }}</dd></div>
                @endif
                @if($company->address)
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Morada</dt>
                    <dd class="text-slate-800">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}{{ $company->state ? ' — '.$company->state : '' }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Colaboradores --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-700 mb-2">Colaboradores ativos</h2>
            <p class="text-2xl font-bold text-slate-800">{{ $company->active_employees_count }}</p>
            <a href="{{ route('painel.employees.index', ['q' => '', 'status' => 'active']) }}"
               class="text-xs text-indigo-600 hover:underline mt-2 inline-block">Gerir colaboradores no painel</a>
        </div>

    </div>

    {{-- Coluna lateral — Gestores + Totems --}}
    <div class="space-y-5">

        {{-- Lista de gestores --}}
        @if($gestores->isNotEmpty())
            @foreach($gestores as $g)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" x-data="{ tab: 'info' }">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm shrink-0">
                        {{ strtoupper(substr($g->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-slate-800 truncate">{{ $g->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ $g->email }}</p>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="flex gap-1 mb-4 bg-slate-100 rounded-lg p-0.5">
                    <button type="button" @click="tab='info'"
                            :class="tab==='info' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Dados</button>
                    <button type="button" @click="tab='pass'"
                            :class="tab==='pass' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Palavra-passe</button>
                </div>

                {{-- Editar dados --}}
                <div x-show="tab==='info'">
                    <form method="post" action="{{ route('painel.companies.gestores.update', [$company, $g]) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Nome</label>
                            <input type="text" name="name" value="{{ old('name', $g->name) }}" required
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">E-mail de login</label>
                            <input type="email" name="email" value="{{ old('email', $g->email) }}" required
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                        </div>
                        <button type="submit"
                                class="w-full text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition">
                            Guardar
                        </button>
                    </form>
                </div>

                {{-- Redefinir senha --}}
                <div x-show="tab==='pass'">
                    <form method="post" action="{{ route('painel.companies.gestores.password', [$company, $g]) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Nova palavra-passe</label>
                            <input type="password" name="password" minlength="8" autocomplete="new-password"
                                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none"
                                   placeholder="Deixe em branco para gerar automaticamente">
                        </div>
                        <button type="submit"
                                class="w-full text-sm font-medium text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg hover:bg-amber-100 transition">
                            Redefinir palavra-passe
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        @else
            <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 text-sm text-slate-500">
                Nenhum gestor registado nesta empresa.
            </div>
        @endif

        {{-- Adicionar novo gestor --}}
        <div class="bg-indigo-50 rounded-xl border border-indigo-100 p-5" x-data="{ open: false }">
            <button type="button" @click="open=!open"
                    class="flex items-center gap-2 text-sm font-medium text-indigo-700 hover:text-indigo-900 transition w-full">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Adicionar novo gestor
            </button>
            <div x-show="open" x-cloak class="mt-4 space-y-3">
                <form method="post" action="{{ route('painel.companies.gestores.add', $company) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nome <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">E-mail <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 outline-none bg-white"
                               placeholder="Deixe em branco para gerar automaticamente">
                    </div>
                    <button type="submit"
                            class="w-full text-sm font-medium text-white bg-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Criar gestor
                    </button>
                </form>
            </div>
        </div>

        {{-- Divisor --}}
        <div class="border-t border-slate-200 pt-2">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Dispositivos Totem</p>
        </div>

        {{-- Lista de totems --}}
        @forelse($totems as $t)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" x-data="{ tab: 'info' }">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-100 text-violet-700 shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-slate-800 truncate">{{ $t->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $t->email }}</p>
                </div>
                @if(!$t->active)
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inativo</span>
                @endif
            </div>

            {{-- Tabs --}}
            <div class="flex gap-1 mb-4 bg-slate-100 rounded-lg p-0.5">
                <button type="button" @click="tab='info'"
                        :class="tab==='info' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Senha</button>
                <button type="button" @click="tab='toggle'"
                        :class="tab==='toggle' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 text-xs font-medium py-1.5 rounded-md transition">Ativar/Desativar</button>
            </div>

            {{-- Redefinir senha --}}
            <div x-show="tab==='info'">
                <form method="post" action="{{ route('painel.companies.totems.password', [$company, $t]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nova senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 focus:border-violet-400 outline-none"
                               placeholder="Deixe em branco para gerar automaticamente">
                    </div>
                    <button type="submit"
                            class="w-full text-sm font-medium text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg hover:bg-amber-100 transition">
                        Redefinir senha
                    </button>
                </form>
            </div>

            {{-- Toggle ativo/inativo --}}
            <div x-show="tab==='toggle'">
                <form method="post" action="{{ route('painel.companies.totems.toggle', [$company, $t]) }}">
                    @csrf
                    <p class="text-xs text-slate-500 mb-3">
                        Status atual: <strong>{{ $t->active ? 'Ativo' : 'Inativo' }}</strong>
                    </p>
                    <button type="submit"
                            class="w-full text-sm font-medium px-3 py-2 rounded-lg transition
                                   {{ $t->active ? 'text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100' : 'text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100' }}">
                        {{ $t->active ? 'Desativar totem' : 'Reativar totem' }}
                    </button>
                </form>
            </div>
        </div>
        @empty
            <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 text-sm text-slate-500">
                Nenhum totem registado nesta empresa.
            </div>
        @endforelse

        {{-- Adicionar totem --}}
        <div class="bg-violet-50 rounded-xl border border-violet-100 p-5" x-data="{ open: false }">
            <button type="button" @click="open=!open"
                    class="flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 transition w-full">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Adicionar novo totem
            </button>
            <div x-show="open" x-cloak class="mt-4 space-y-3">
                <form method="post" action="{{ route('painel.companies.totems.add', $company) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nome do dispositivo <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required placeholder="Ex: Totem Recepção"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">E-mail de login <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" required
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Senha</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password"
                               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-200 outline-none bg-white"
                               placeholder="Deixe em branco para gerar automaticamente">
                    </div>
                    <button type="submit"
                            class="w-full text-sm font-medium text-white bg-violet-600 px-3 py-2 rounded-lg hover:bg-violet-700 transition">
                        Criar totem
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

{{-- Alpine.js para as tabs e accordion --}}
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

@endsection
