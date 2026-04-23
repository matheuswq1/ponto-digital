<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — Ponto Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-indigo-900 via-indigo-800 to-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-sm">
    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-700 to-indigo-600 px-8 py-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Ponto Digital</h1>
            <p class="mt-1 text-indigo-200 text-sm">Acesso ao painel de gestão</p>
        </div>

        {{-- Form --}}
        <div class="px-8 py-8">
            @if($errors->any())
                <div class="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ url('/login') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900
                               placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                        placeholder="seu@email.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Senha</label>
                    <input type="password" name="password" required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900
                               placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                        placeholder="••••••••">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Lembrar-me
                    </label>
                </div>
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold
                           rounded-lg py-2.5 text-sm transition-colors shadow-sm">
                    Entrar no painel
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">Use as mesmas credenciais do app móvel</p>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-indigo-300">Ponto Digital &copy; {{ date('Y') }}</p>
</div>

</body>
</html>
