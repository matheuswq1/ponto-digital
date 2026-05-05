<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') — RM Colaboradores</title>
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
        /* ── Sidebar base ─────────────────────────────────────────────── */
        #sidebar {
            background: linear-gradient(180deg, #0f172a 0%, #131c2e 60%, #0f172a 100%);
        }

        /* Scrollbar fina */
        #sidebar-nav::-webkit-scrollbar { width: 3px; }
        #sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }

        /* ── Item de navegação ────────────────────────────────────────── */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.42rem 0.7rem;
            border-radius: 0.5rem;
            font-size: 0.78rem;
            font-weight: 500;
            color: rgba(148,163,184,0.85);   /* slate-400 */
            transition: background 140ms, color 140ms;
            position: relative;
            text-decoration: none;
            white-space: nowrap;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.06);
            color: #e2e8f0;
        }
        .nav-item.active {
            background: rgba(99,102,241,0.18);
            color: #fff;
            font-weight: 600;
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: #818cf8;
        }
        .nav-item .nav-icon {
            width: 14px; height: 14px; flex-shrink: 0;
            color: rgba(100,116,139,0.9);
            transition: color 140ms;
        }
        .nav-item:hover .nav-icon { color: #94a3b8; }
        .nav-item.active .nav-icon { color: #818cf8; }

        /* ── Sub-itens com linha conectora ───────────────────────────── */
        .nav-sub-wrap {
            position: relative;
            padding-left: 1rem;
            margin-left: 1.1rem;
        }
        .nav-sub-wrap::before {
            content: '';
            position: absolute;
            left: 0; top: 6px; bottom: 6px;
            width: 1px;
            background: rgba(255,255,255,0.07);
            border-radius: 99px;
        }
        .nav-sub {
            padding-left: 0.65rem;
        }

        /* ── Trigger do grupo colapsável ─────────────────────────────── */
        .nav-group-trigger {
            display: flex; align-items: center; gap: 0.65rem;
            width: 100%; padding: 0.48rem 0.7rem;
            border-radius: 0.5rem; border: none; background: none; cursor: pointer;
            font-size: 0.78rem; font-weight: 600;
            color: rgba(100,116,139,0.9);
            transition: background 140ms, color 140ms;
            text-align: left;
        }
        .nav-group-trigger:hover {
            background: rgba(255,255,255,0.05);
            color: #cbd5e1;
        }
        .nav-group-trigger.has-active { color: #c7d2fe; }
        .nav-group-trigger .trigger-icon-wrap {
            display: flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; flex-shrink: 0;
            border-radius: 7px;
            background: rgba(255,255,255,0.05);
            transition: background 140ms;
        }
        .nav-group-trigger:hover .trigger-icon-wrap,
        .nav-group-trigger.has-active .trigger-icon-wrap { background: rgba(255,255,255,0.08); }
        .nav-group-trigger .nav-icon { width: 13px; height: 13px; flex-shrink: 0; }
        .nav-group-chevron {
            width: 11px; height: 11px; margin-left: auto; flex-shrink: 0;
            color: rgba(100,116,139,0.5);
            transition: transform 220ms cubic-bezier(.4,0,.2,1), color 140ms;
        }
        .nav-group-trigger:hover .nav-group-chevron { color: rgba(148,163,184,0.7); }
        .nav-group-trigger.open .nav-group-chevron { transform: rotate(180deg); }

        /* ── Animação do painel de sub-itens ─────────────────────────── */
        .nav-group-items {
            overflow: hidden;
            max-height: 0;
            transition: max-height 240ms cubic-bezier(.4,0,.2,1);
        }
        .nav-group-items.open { max-height: 700px; }

        /* ── Separador de grupo ───────────────────────────────────────── */
        .nav-divider {
            margin: 0.4rem 0.75rem;
            border: none;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* ── Cores por secção (ícone do trigger) ─────────────────────── */
        .group-color-blue   .nav-icon { color: #60a5fa; }
        .group-color-teal   .nav-icon { color: #34d399; }
        .group-color-amber  .nav-icon { color: #fbbf24; }
        .group-color-slate  .nav-icon { color: #94a3b8; }

        /* ── Badge ───────────────────────────────────────────────────── */
        .nav-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 17px; height: 17px;
            border-radius: 99px;
            font-size: 9px; font-weight: 700;
            padding: 0 4px; line-height: 1;
            flex-shrink: 0;
        }
        .nav-badge-rose  { background: rgba(239,68,68,0.2);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .nav-badge-amber { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .nav-badge-sky   { background: rgba(14,165,233,0.2); color: #38bdf8; border: 1px solid rgba(14,165,233,0.3); }
    </style>
</head>
<body class="h-full bg-slate-100">

{{-- ===== MOBILE OVERLAY ===== --}}
<div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black/50 lg:hidden hidden"></div>

{{-- ===== SIDEBAR ===== --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-30 w-64 flex flex-col
              shadow-[6px_0_32px_rgba(0,0,0,0.45)]
              -translate-x-full lg:translate-x-0
              transition-transform duration-250 ease-in-out">

    {{-- ── LOGO ── --}}
    <div class="flex items-center gap-3 px-4 py-4 shrink-0"
         style="border-bottom:1px solid rgba(255,255,255,0.06)">
        <div class="relative flex h-9 w-9 items-center justify-center rounded-xl flex-shrink-0"
             style="background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 0 0 1px rgba(255,255,255,0.1) inset,0 4px 12px rgba(37,99,235,0.4)">
            <span class="text-white font-black text-sm tracking-tight leading-none select-none">RM</span>
            <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-70"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
            </span>
        </div>
        <div class="min-w-0">
            <p class="text-white font-bold text-[13px] leading-tight tracking-wide">RM Colaboradores</p>
            <p class="text-[11px] leading-tight mt-0.5" style="color:rgba(100,116,139,0.9)">Painel de Gestão</p>
        </div>
    </div>

    {{-- ── NAV ── --}}
    @php
        $pendingBadge    = \App\Models\TimeRecordEdit::where('status','pendente')->count();
        $pendingAdditions= \App\Models\TimeRecordAddition::where('status','pendente')->count();
        $pendingHourBank = \App\Models\HourBankRequest::where('status','pendente')->count();
        $pendingVacation = class_exists('\App\Models\VacationRequest') ? \App\Models\VacationRequest::where('status','pendente')->count() : 0;
        $totalPontoBadge = $pendingBadge + $pendingAdditions + $pendingHourBank;

        $inPonto    = request()->routeIs(['painel.pontos.*','painel.edit-requests.*','painel.addition-requests.*','painel.hour-bank.*','painel.audit.*','painel.fraud-alerts.*']);
        $inPessoas  = request()->routeIs(['painel.employees.*','painel.departments.*','painel.payslips.*','painel.communications.*','painel.admin-push.*','painel.vacation-requests.*']);
        $inRelat    = request()->routeIs(['painel.reports.*','painel.holidays.*']);
        $inConfig   = request()->routeIs(['painel.companies.*','painel.users.*']);
    @endphp

    <nav id="sidebar-nav" class="flex-1 overflow-y-auto px-2.5 py-3 space-y-0.5">

        {{-- Dashboard --}}
        <a href="{{ route('painel.dashboard') }}"
           class="nav-item {{ request()->routeIs('painel.dashboard') ? 'active' : '' }}">
            <span class="flex items-center justify-center w-[26px] h-[26px] rounded-lg flex-shrink-0"
                  style="background:rgba(99,102,241,0.15)">
                <svg class="w-[13px] h-[13px]" style="color:#818cf8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
            </span>
            <span>Dashboard</span>
        </a>

        @if(auth()->user()->isAdmin() || auth()->user()->isGestor())

        <hr class="nav-divider">

        {{-- ══ GRUPO: PONTO ══ --}}
        <div class="nav-group" data-group="ponto">
            <button type="button"
                    class="nav-group-trigger group-color-blue {{ $inPonto ? 'has-active open' : '' }}"
                    onclick="navToggle('ponto')">
                <span class="trigger-icon-wrap" style="{{ $inPonto ? 'background:rgba(96,165,250,0.15)' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </span>
                <span class="flex-1">Ponto</span>
                @if($totalPontoBadge > 0 && !$inPonto)
                    <span class="nav-badge nav-badge-rose">{{ $totalPontoBadge > 99 ? '99+' : $totalPontoBadge }}</span>
                @endif
                <svg class="nav-group-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="nav-group-items {{ $inPonto ? 'open' : '' }}" id="nav-group-ponto">
                <div class="nav-sub-wrap py-0.5 space-y-0.5">
                    <a href="{{ route('painel.pontos.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.pontos.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>
                        <span>Registros</span>
                    </a>
                    <a href="{{ route('painel.edit-requests.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.edit-requests.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                        </svg>
                        <span class="flex-1">Correções</span>
                        @if($pendingBadge > 0)
                            <span class="nav-badge nav-badge-rose">{{ $pendingBadge > 99 ? '99+' : $pendingBadge }}</span>
                        @endif
                    </a>
                    <a href="{{ route('painel.addition-requests.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.addition-requests.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span class="flex-1">Adições</span>
                        @if($pendingAdditions > 0)
                            <span class="nav-badge nav-badge-amber">{{ $pendingAdditions > 99 ? '99+' : $pendingAdditions }}</span>
                        @endif
                    </a>
                    <a href="{{ route('painel.hour-bank.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.hour-bank.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span class="flex-1">Banco de Horas</span>
                        @if($pendingHourBank > 0)
                            <span class="nav-badge nav-badge-amber">{{ $pendingHourBank > 99 ? '99+' : $pendingHourBank }}</span>
                        @endif
                    </a>
                    @can('view-audit-logs')
                    <a href="{{ route('painel.audit.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.audit.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l2.25-2.25M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span>Auditoria</span>
                    </a>
                    <a href="{{ route('painel.fraud-alerts.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.fraud-alerts.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        <span>Fraudes</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- ══ GRUPO: PESSOAS & RH ══ --}}
        <div class="nav-group" data-group="pessoas">
            <button type="button"
                    class="nav-group-trigger group-color-teal {{ $inPessoas ? 'has-active open' : '' }}"
                    onclick="navToggle('pessoas')">
                <span class="trigger-icon-wrap" style="{{ $inPessoas ? 'background:rgba(52,211,153,0.15)' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                </span>
                <span class="flex-1">Pessoas & RH</span>
                @if($pendingVacation > 0 && !$inPessoas)
                    <span class="nav-badge nav-badge-sky">{{ $pendingVacation }}</span>
                @endif
                <svg class="nav-group-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="nav-group-items {{ $inPessoas ? 'open' : '' }}" id="nav-group-pessoas">
                <div class="nav-sub-wrap py-0.5 space-y-0.5">
                    <a href="{{ route('painel.employees.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.employees.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <span>Colaboradores</span>
                    </a>
                    <a href="{{ route('painel.departments.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.departments.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5M2.25 12h19.5m-19.5 4.5h19.5M4.5 2.25h4.5v4.5H4.5V2.25Zm10.5 0h4.5v4.5H15V2.25Zm-10.5 15h4.5v4.5H4.5v-4.5Zm10.5 0h4.5v4.5H15v-4.5Z"/>
                        </svg>
                        <span>Departamentos</span>
                    </a>
                    <a href="{{ route('painel.payslips.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.payslips.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                        <span>Holerites</span>
                    </a>
                    <a href="{{ route('painel.communications.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.communications.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46"/>
                        </svg>
                        <span>Comunicados</span>
                    </a>
                    <a href="{{ route('painel.admin-push.create') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.admin-push.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                        </svg>
                        <span>Notificar app</span>
                    </a>
                    <a href="{{ route('painel.vacation-requests.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.vacation-requests.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                        </svg>
                        <span class="flex-1">Férias</span>
                        @if($pendingVacation > 0)
                            <span class="nav-badge nav-badge-sky">{{ $pendingVacation > 99 ? '99+' : $pendingVacation }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        {{-- ══ GRUPO: RELATÓRIOS ══ --}}
        <div class="nav-group" data-group="relat">
            <button type="button"
                    class="nav-group-trigger group-color-amber {{ $inRelat ? 'has-active open' : '' }}"
                    onclick="navToggle('relat')">
                <span class="trigger-icon-wrap" style="{{ $inRelat ? 'background:rgba(251,191,36,0.15)' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                    </svg>
                </span>
                <span class="flex-1">Relatórios</span>
                <svg class="nav-group-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="nav-group-items {{ $inRelat ? 'open' : '' }}" id="nav-group-relat">
                <div class="nav-sub-wrap py-0.5 space-y-0.5">
                    <a href="{{ route('painel.reports.folha-pagamento') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.reports.folha-pagamento') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
                        </svg>
                        <span>Folha de Pagamento</span>
                    </a>
                    <a href="{{ route('painel.reports.presenca') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.reports.presenca') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/>
                        </svg>
                        <span>Presença</span>
                    </a>
                    <a href="{{ route('painel.reports.banco-horas') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.reports.banco-horas') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span>Banco de Horas</span>
                    </a>
                    <a href="{{ route('painel.reports.espelho-ponto') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.reports.espelho-ponto') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <span>Espelho de Ponto</span>
                    </a>
                    <a href="{{ route('painel.holidays.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.holidays.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        <span>Feriados</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ══ GRUPO: CONFIGURAÇÕES ══ --}}
        @if(auth()->user()->isAdmin())
        <div class="nav-group" data-group="config">
            <button type="button"
                    class="nav-group-trigger group-color-slate {{ $inConfig ? 'has-active open' : '' }}"
                    onclick="navToggle('config')">
                <span class="trigger-icon-wrap" style="{{ $inConfig ? 'background:rgba(148,163,184,0.15)' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </span>
                <span class="flex-1">Configurações</span>
                <svg class="nav-group-chevron" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="nav-group-items {{ $inConfig ? 'open' : '' }}" id="nav-group-config">
                <div class="nav-sub-wrap py-0.5 space-y-0.5">
                    @can('manage-companies')
                    <a href="{{ route('painel.companies.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.companies.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008Zm0 3.75h.008v.008H17.25v-.008Zm0 3.75h.008v.008H17.25v-.008Z"/>
                        </svg>
                        <span>Empresas</span>
                    </a>
                    @endcan
                    <a href="{{ route('painel.users.index') }}"
                       class="nav-item nav-sub {{ request()->routeIs('painel.users.*') ? 'active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <span>Utilizadores</span>
                    </a>
                </div>
            </div>
        </div>
        @endif

        @endif {{-- isAdmin || isGestor --}}

        <div class="h-3"></div>
    </nav>

    {{-- ── USER FOOTER ── --}}
    <div class="shrink-0 px-2.5 py-3" style="border-top:1px solid rgba(255,255,255,0.06)">
        <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl transition-colors"
             style="background:rgba(255,255,255,0.04)">
            {{-- Avatar --}}
            <div class="relative shrink-0">
                <div class="flex h-8 w-8 items-center justify-center rounded-full font-bold text-xs text-white select-none"
                     style="background:linear-gradient(135deg,#4f46e5,#7c3aed);box-shadow:0 2px 8px rgba(99,102,241,0.4)">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3 items-center justify-center rounded-full ring-2"
                      style="background:{{ auth()->user()->isAdmin() ? '#f59e0b' : (auth()->user()->isGestor() ? '#38bdf8' : '#64748b') }};ring-color:#0f172a">
                </span>
            </div>

            <div class="flex-1 min-w-0">
                <p class="text-white text-[12px] font-semibold truncate leading-tight">
                    {{ auth()->user()->name ?? '' }}
                </p>
                <p class="text-[11px] truncate leading-tight" style="color:rgba(100,116,139,0.9)">
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
                <button type="submit" title="Encerrar sessão"
                        class="flex items-center justify-center w-7 h-7 rounded-lg transition-colors"
                        style="color:rgba(100,116,139,0.7)"
                        onmouseover="this.style.background='rgba(239,68,68,0.12)';this.style.color='#f87171'"
                        onmouseout="this.style.background='transparent';this.style.color='rgba(100,116,139,0.7)'">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                    </svg>
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
        <span>RM Colaboradores &copy; {{ date('Y') }} &mdash; v2.0</span>
        <span class="hidden sm:inline">Painel de gestão &amp; RH</span>
    </footer>
</div>

{{-- ── SCRIPT SIDEBAR MOBILE + GRUPOS ── --}}
<script>
(function () {
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebar-overlay');
    const hamburger = document.getElementById('hamburger');

    function openSidebar()  {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    hamburger?.addEventListener('click', () =>
        sidebar.classList.contains('-translate-x-full') ? openSidebar() : closeSidebar()
    );
    overlay?.addEventListener('click', closeSidebar);
    sidebar?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        if (window.innerWidth < 1024) closeSidebar();
    }));
})();

// Grupos colapsáveis da sidebar
function navToggle(group) {
    const items   = document.getElementById('nav-group-' + group);
    const trigger = items?.previousElementSibling;
    if (!items || !trigger) return;

    const isOpen = items.classList.contains('open');
    items.classList.toggle('open', !isOpen);
    trigger.classList.toggle('open', !isOpen);

    // Persiste no localStorage
    try { localStorage.setItem('nav_' + group, isOpen ? '0' : '1'); } catch(e){}
}

// Restaura estado salvo (apenas para grupos que não têm rota activa)
(function () {
    document.querySelectorAll('[data-group]').forEach(function(grp) {
        const g       = grp.dataset.group;
        const trigger = grp.querySelector('.nav-group-trigger');
        const items   = document.getElementById('nav-group-' + g);
        if (!items || !trigger) return;

        // Se já está aberto por rota activa, não altera
        if (items.classList.contains('open')) return;

        try {
            if (localStorage.getItem('nav_' + g) === '1') {
                items.classList.add('open');
                trigger.classList.add('open');
            }
        } catch(e) {}
    });
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
