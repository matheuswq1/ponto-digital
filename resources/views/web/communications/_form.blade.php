<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

    {{-- Empresa (admin) --}}
    @if(auth()->user()->isAdmin())
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Empresa <span class="text-rose-500">*</span></label>
        @php $companies = \App\Models\Company::where('active',true)->orderBy('name')->get(); @endphp
        <select name="company_id" required class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 outline-none">
            <option value="">Selecione…</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(old('company_id', $communication->company_id ?? '') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- Título --}}
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Título <span class="text-rose-500">*</span></label>
        <input type="text" name="title" maxlength="160" required
               value="{{ old('title', $communication->title ?? '') }}"
               placeholder="Ex: Reunião de equipe na sexta-feira"
               class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 outline-none">
    </div>

    {{-- Corpo --}}
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mensagem <span class="text-rose-500">*</span></label>
        <textarea name="body" rows="6" required maxlength="5000"
                  placeholder="Escreva o conteúdo do comunicado…"
                  class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 outline-none resize-none">{{ old('body', $communication->body ?? '') }}</textarea>
    </div>

    {{-- Tipo + Fixado --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tipo</label>
            <select name="type" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 outline-none">
                <option value="info"    @selected(old('type', $communication->type ?? 'info') == 'info')>Informativo</option>
                <option value="aviso"   @selected(old('type', $communication->type ?? '') == 'aviso')>Aviso</option>
                <option value="urgente" @selected(old('type', $communication->type ?? '') == 'urgente')>Urgente</option>
            </select>
        </div>
        <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <div class="relative">
                    <input type="hidden" name="pinned" value="0">
                    <input type="checkbox" name="pinned" value="1" class="sr-only peer"
                           @checked(old('pinned', $communication->pinned ?? false))>
                    <div class="w-10 h-5 bg-slate-200 peer-checked:bg-indigo-500 rounded-full transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                </div>
                <span class="text-sm text-slate-600 font-medium">Fixar no topo</span>
            </label>
        </div>
    </div>

    {{-- Publicação --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Publicar em</label>
            <div class="flex items-center gap-2 mb-2">
                <input type="checkbox" name="publish_now" id="publish-now" value="1"
                       class="rounded border-slate-300"
                       @checked(old('publish_now', !isset($communication) || $communication->published_at))>
                <label for="publish-now" class="text-sm text-slate-600">Publicar imediatamente</label>
            </div>
            <input type="datetime-local" name="published_at"
                   value="{{ old('published_at', isset($communication) && $communication->published_at ? $communication->published_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 outline-none">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Expirar em <span class="text-slate-400 font-normal">(opcional)</span></label>
            <input type="datetime-local" name="expires_at"
                   value="{{ old('expires_at', isset($communication) && $communication->expires_at ? $communication->expires_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 outline-none">
        </div>
    </div>
</div>
