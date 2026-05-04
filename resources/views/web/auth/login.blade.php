<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — RM Colaboradores</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 min-h-screen" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1a1a2e 100%);">

{{-- Elementos decorativos de fundo --}}
<div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, #2563eb, transparent)"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, #dc2626, transparent)"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full opacity-5" style="background: radial-gradient(circle, #3b82f6, transparent)"></div>
</div>

<div class="w-full max-w-sm relative z-10">

    {{-- Logo / Marca --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center mb-4">
            {{-- Ícone RM --}}
            <div class="relative">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center shadow-2xl"
                     style="background: linear-gradient(135deg, #1d4ed8, #2563eb)">
                    <span class="text-white font-black text-2xl tracking-tight leading-none">RM</span>
                </div>
                {{-- Badge vermelho --}}
                <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center shadow-lg"
                     style="background: #dc2626">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </div>
            </div>
        </div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">RM Colaboradores</h1>
        <p class="text-slate-400 text-sm mt-1">Gestão de pessoas simplificada</p>
    </div>

    {{-- Card --}}
    <div class="bg-white/[0.05] backdrop-blur-xl rounded-2xl border border-white/10 shadow-2xl overflow-hidden">

        {{-- Barra de destaque topo --}}
        <div class="h-1 w-full" style="background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 50%, #dc2626 100%)"></div>

        <div class="px-8 py-8">

            <h2 class="text-lg font-bold text-white mb-1">Acesso ao painel</h2>
            <p class="text-slate-400 text-xs mb-6">Entre com suas credenciais de administrador</p>

            @if($errors->any())
                <div class="mb-5 rounded-xl border px-4 py-3 text-sm flex items-start gap-2.5"
                     style="background: rgba(220,38,38,0.1); border-color: rgba(220,38,38,0.3); color: #fca5a5;">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ url('/login') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition"
                        style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);"
                        onfocus="this.style.borderColor='rgba(37,99,235,0.7)'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.15)'"
                        onblur="this.style.borderColor='rgba(255,255,255,0.12)'; this.style.boxShadow='none'"
                        placeholder="seu@email.com">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Senha</label>
                    <input type="password" name="password" required
                        class="w-full rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition"
                        style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);"
                        onfocus="this.style.borderColor='rgba(37,99,235,0.7)'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.15)'"
                        onblur="this.style.borderColor='rgba(255,255,255,0.12)'; this.style.boxShadow='none'"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer select-none">
                        <input type="checkbox" name="remember"
                               class="w-4 h-4 rounded border-slate-600 focus:ring-blue-600"
                               style="accent-color: #2563eb;">
                        Lembrar-me
                    </label>
                </div>

                <button type="submit"
                    class="w-full text-white font-bold rounded-xl py-3 text-sm transition-all shadow-lg mt-2"
                    style="background: linear-gradient(135deg, #1d4ed8, #2563eb);"
                    onmouseover="this.style.opacity='0.9'; this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.opacity='1'; this.style.transform='translateY(0)'">
                    Entrar no painel
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-white/10 flex items-center justify-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full" style="background:#2563eb"></div>
                <p class="text-center text-xs text-slate-500">Use as mesmas credenciais do app mobile</p>
                <div class="w-1.5 h-1.5 rounded-full" style="background:#dc2626"></div>
            </div>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-slate-600">RM Colaboradores &copy; {{ date('Y') }} — Todos os direitos reservados</p>
</div>

</body>
</html>
