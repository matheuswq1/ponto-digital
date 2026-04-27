<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') — Ponto Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Scrollbar fina na sidebar */
        #sidebar-nav::-webkit-scrollbar { width: 4px; }
        #sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

        /* Item de navegação */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.875rem;
            border-radius: 0.625rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgba(199,210,254,0.85); /* brand-200 com alpha */
            transition: background 150ms, color 150ms;
            position: relative;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .nav-item.active {
            background: rgba(255,255,255,0.13);
            color: #fff;
            box-shadow: inset 3px 0 0 #818cf8; /* brand-400 */
        }
        .nav-item.active .nav-icon { color: #a5b4fc; }
        .nav-item .nav-icon {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            color: rgba(165,180,252,0.7);
            transition: color 150ms;
        }
        .nav-item:hover .nav-icon { color: #c7d2fe; }

        /* Separador de secção */
        .nav-section {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(165,180,252,0.5);
            padding: 1rem 0.875rem 0.375rem;
        }
    </style>
</head>
<body class="h-full bg-slate-100">

{{-- ===== MOBILE OVERLAY ===== --}}
<div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black/50 lg:hidden hidden"></div>

{{-- ===== SIDEBAR ===== --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-30 w-64 flex flex-col
              bg-brand-950
              shadow-[4px_0_24px_rgba(0,0,0,0.35)]
              -translate-x-full lg:translate-x-0
              transition-transform duration-250 ease-in-out">

    {{-- ── LOGO ── --}}
    <div class="flex items-center gap-3 px-5 py-4 border-b border-white/[0.07] shrink-0">
        <div class="relative flex h-10 w-10 items-center justify-center rounded-xl
                    bg-gradient-to-br from-brand-500 to-brand-700 shadow-lg shadow-brand-900/60">
            <svg class="w-5 h-5 text-white drop-shadow" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            {{-- Pulso de status online --}}
            <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            </span>
        </div>
        <div class="min-w-0">
            <p class="text-white font-bold text-sm leading-tight tracking-wide">Ponto Digital</p>
            <p class="text-brand-400 text-[11px] leading-tight mt-0.5">Painel de gestão</p>
        </div>
    </div>

    {{-- ── NAV ── --}}
    <nav id="sidebar-nav" class="flex-1 overflow-y-auto px-3 py-2">

        {{-- Geral --}}
        <p class="nav-section">Geral</p>

        <a href="{{ route('painel.dashboard') }}"
           class="nav-item {{ request()->routeIs('painel.dashboard') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
            </svg>
            <span>Dashboard</span>
        </a>

        @if(auth()->user()->isAdmin() || auth()->user()->isGestor())

        {{-- Gestão --}}
        <p class="nav-section" style="margin-top:0.5rem">Gestão</p>

        @php $pendingBadge = \App\Models\TimeRecordEdit::where('status','pendente')->count(); @endphp
        <a href="{{ route('painel.edit-requests.index') }}"
           class="nav-item {{ request()->routeIs('painel.edit-requests.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
            </svg>
            <span class="flex-1">Correções</span>
            @if($pendingBadge > 0)
                <span class="ml-auto inline-flex items-center justify-center h-[18px] min-w-[18px] rounded-full
                             bg-rose-500 text-white text-[10px] font-bold px-1 shadow shadow-rose-900/40">
                    {{ $pendingBadge > 99 ? '99+' : $pendingBadge }}
                </span>
            @endif
        </a>

        <a href="{{ route('painel.employees.index') }}"
           class="nav-item {{ request()->routeIs('painel.employees.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
            </svg>
            <span>Colaboradores</span>
        </a>

        <a href="{{ route('painel.departments.index') }}"
           class="nav-item {{ request()->routeIs('painel.departments.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5M2.25 12h19.5m-19.5 4.5h19.5M4.5 2.25h4.5v4.5H4.5V2.25Zm10.5 0h4.5v4.5H15V2.25Zm-10.5 15h4.5v4.5H4.5v-4.5Zm10.5 0h4.5v4.5H15v-4.5Z"/>
            </svg>
            <span>Departamentos</span>
        </a>

        @php $pendingHourBank = \App\Models\HourBankRequest::where('status','pendente')->count(); @endphp
        <a href="{{ route('painel.hour-bank.index') }}"
           class="nav-item {{ request()->routeIs('painel.hour-bank.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="flex-1">Banco de Horas</span>
            @if($pendingHourBank > 0)
                <span class="ml-auto inline-flex items-center justify-center h-[18px] min-w-[18px] rounded-full
                             bg-amber-500 text-white text-[10px] font-bold px-1 shadow shadow-amber-900/40">
                    {{ $pendingHourBank > 99 ? '99+' : $pendingHourBank }}
                </span>
            @endif
        </a>

        <a href="{{ route('painel.pontos.index') }}"
           class="nav-item {{ request()->routeIs('painel.pontos.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span>Registros de ponto</span>
        </a>

        @can('view-audit-logs')
        <a href="{{ route('painel.audit.index') }}"
           class="nav-item {{ request()->routeIs('painel.audit.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l2.25-2.25M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span>Auditoria</span>
        </a>
        <a href="{{ route('painel.fraud-alerts.index') }}"
           class="nav-item {{ request()->routeIs('painel.fraud-alerts.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <span>Alertas de Fraude</span>
        </a>
        @endcan

        <p class="nav-section" style="margin-top:0.5rem">Relatórios</p>

        <a href="{{ route('painel.reports.folha-pagamento') }}"
           class="nav-item {{ request()->routeIs('painel.reports.folha-pagamento') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
            </svg>
            <span>Folha de Pagamento</span>
        </a>

        <a href="{{ route('painel.reports.presenca') }}"
           class="nav-item {{ request()->routeIs('painel.reports.presenca') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/>
            </svg>
            <span>Presença / Ausência</span>
        </a>

        <a href="{{ route('painel.reports.banco-horas') }}"
           class="nav-item {{ request()->routeIs('painel.reports.banco-horas') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span>Extrato banco de horas</span>
        </a>

        <a href="{{ route('painel.holidays.index') }}"
           class="nav-item {{ request()->routeIs('painel.holidays.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span>Feriados</span>
        </a>

        @if(auth()->user()->isAdmin())
        @can('manage-companies')
        <a href="{{ route('painel.companies.index') }}"
           class="nav-item {{ request()->routeIs('painel.companies.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008Zm0 3.75h.008v.008H17.25v-.008Zm0 3.75h.008v.008H17.25v-.008Z"/>
            </svg>
            <span>Empresas</span>
        </a>
        @endcan
        <a href="{{ route('painel.users.index') }}"
           class="nav-item {{ request()->routeIs('painel.users.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <span>Utilizadores</span>
        </a>
        @endif

        @endif

        {{-- Espaço antes do footer --}}
        <div class="h-4"></div>
    </nav>

    {{-- ── USER FOOTER ── --}}
    <div class="shrink-0 border-t border-white/[0.07] px-3 py-3">
        {{-- Card do utilizador --}}
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] transition group">
            {{-- Avatar com gradiente --}}
            <div class="relative shrink-0">
                <div class="flex h-9 w-9 items-center justify-center rounded-full
                            bg-gradient-to-br from-brand-400 to-brand-600
                            text-white font-bold text-sm shadow shadow-brand-900/50">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                {{-- Badge de role --}}
                <span class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full
                             {{ auth()->user()->isAdmin() ? 'bg-amber-400' : (auth()->user()->isGestor() ? 'bg-sky-400' : 'bg-slate-500') }}
                             ring-2 ring-brand-950">
                </span>
            </div>

            <div class="flex-1 min-w-0">
                <p class="text-white text-xs font-semibold truncate leading-tight">
                    {{ auth()->user()->name ?? '' }}
                </p>
                <p class="text-brand-400 text-[11px] truncate leading-tight mt-0.5">
                    @switch(auth()->user()->role)
                        @case('admin') Administrador @break
                        @case('gestor') Gestor de RH @break
                        @case('funcionario') Colaborador @break
                        @default {{ ucfirst(auth()->user()->role ?? '') }}
                    @endswitch
                </p>
            </div>

            {{-- Botão sair --}}
            <form method="post" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        title="Encerrar sessão"
                        class="flex items-center gap-1.5 rounded-lg px-2 py-1.5
                               text-brand-400 hover:text-rose-400 hover:bg-rose-400/10
                               transition text-[11px] font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                    </svg>
                    Sair
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ===== CONTEÚDO PRINCIPAL ===== --}}
<div class="lg:pl-64 flex flex-col min-h-screen">

    {{-- ── TOPBAR ── --}}
    <header class="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3
                   border-b border-slate-200 bg-white/80 backdrop-blur px-4 shadow-sm">
        {{-- Hamburger mobile --}}
        <button id="hamburger"
                class="lg:hidden flex h-8 w-8 items-center justify-center rounded-lg
                       text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
        </button>

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-400 hidden sm:inline">Painel</span>
            <span class="text-slate-300 hidden sm:inline">/</span>
            <span class="font-semibold text-slate-700">@yield('page-title', 'Início')</span>
        </div>

        <div class="ml-auto flex items-center gap-3">
            {{-- Data --}}
            <div class="hidden md:flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs text-slate-500">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                {{ now()->locale('pt_BR')->isoFormat('ddd, D [de] MMM [de] YYYY') }}
            </div>

            {{-- Avatar mini (topbar) --}}
            <div class="flex h-8 w-8 items-center justify-center rounded-full
                        bg-gradient-to-br from-brand-500 to-brand-700 text-white text-xs font-bold shadow">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </div>
        </div>
    </header>

    {{-- ── PAGE CONTENT ── --}}
    <main class="flex-1 p-5 lg:p-6">

        @if(session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 shadow-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 shadow-sm">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ── FOOTER ── --}}
    <footer class="shrink-0 flex items-center justify-between px-6 py-2.5
                   text-[11px] text-slate-400 border-t border-slate-200 bg-white">
        <span>Ponto Digital &copy; {{ date('Y') }} &mdash; v1.0</span>
        <span class="hidden sm:inline">Painel de gestão &amp; RH</span>
    </footer>
</div>

{{-- ── SCRIPT SIDEBAR MOBILE ── --}}
<script>
(function () {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const hamburger = document.getElementById('hamburger');

    function open()  {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    hamburger?.addEventListener('click', () =>
        sidebar.classList.contains('-translate-x-full') ? open() : close()
    );
    overlay?.addEventListener('click', close);

    // Fecha ao clicar num link na sidebar (mobile)
    sidebar?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        if (window.innerWidth < 1024) close();
    }));
})();
</script>

{{-- ── Máscara de data brasileira (dd/mm/aaaa) ── --}}
<script>
(function () {
    function initDateBr() {
        document.querySelectorAll('[data-datebr]').forEach(function (inp) {
            if (inp.dataset.dateBrInit) return;
            inp.dataset.dateBrInit = '1';

            var hidden = document.getElementById(inp.id + '_iso');

            // Máscara ao digitar
            inp.addEventListener('input', function () {
                var v = inp.value.replace(/\D/g, '').substring(0, 8);
                if (v.length > 4)      v = v.slice(0,2) + '/' + v.slice(2,4) + '/' + v.slice(4);
                else if (v.length > 2) v = v.slice(0,2) + '/' + v.slice(2);
                inp.value = v;
                syncIso(v);
            });

            // Ao sair do campo: valida e formata
            inp.addEventListener('blur', function () {
                syncIso(inp.value);
            });

            function syncIso(br) {
                if (!hidden) return;
                var parts = br.split('/');
                if (parts.length === 3 && parts[2].length === 4) {
                    var d = parseInt(parts[0],10), m = parseInt(parts[1],10), y = parseInt(parts[2],10);
                    if (d >= 1 && d <= 31 && m >= 1 && m <= 12 && y >= 1900) {
                        hidden.value = y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                        return;
                    }
                }
                hidden.value = '';
            }
        });
    }

    // Inicializar no carregamento e depois de qualquer mutação DOM (Alpine, etc.)
    document.addEventListener('DOMContentLoaded', initDateBr);
    var obs = new MutationObserver(initDateBr);
    obs.observe(document.body, { childList: true, subtree: true });
})();
</script>

</body>
</html>
