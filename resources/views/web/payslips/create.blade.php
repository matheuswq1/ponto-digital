@extends('web.layout')
@section('title', 'Enviar Holerites')
@section('page-title', 'Enviar Holerites')

@section('content')
<div class="max-w-2xl">

@if($errors->any())
<div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
    <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="post" action="{{ route('painel.payslips.store') }}" enctype="multipart/form-data" class="space-y-5" id="payslip-form">
    @csrf

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

        {{-- Empresa --}}
        @if(auth()->user()->isAdmin())
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Empresa <span class="text-rose-500">*</span></label>
            <select name="company_id" id="company-sel" required
                    class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                <option value="">Selecione a empresa…</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @else
        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
        @endif

        {{-- Colaboradores --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Colaboradores <span class="text-rose-500">*</span></label>
            <div class="relative">
                <select name="employee_ids[]" id="employee-sel" multiple required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none min-h-[100px]">
                    @if(!auth()->user()->isAdmin())
                        @php
                            $emps = \App\Models\Employee::where('company_id', auth()->user()->company_id)
                                        ->where('active', true)->with('user')->get();
                        @endphp
                        @foreach($emps as $e)
                            <option value="{{ $e->id }}" @selected(in_array($e->id, old('employee_ids', [])))>
                                {{ $e->user?->name ?? 'Funcionário #'.$e->id }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <p class="mt-1.5 text-xs text-slate-400">Segure Ctrl (Windows) ou Cmd (Mac) para selecionar múltiplos colaboradores. Será necessário um arquivo PDF por colaborador (na ordem da seleção), ou um único PDF que será enviado para todos.</p>
        </div>

        {{-- Período --}}
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mês de referência <span class="text-rose-500">*</span></label>
                <select name="reference_month" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                    @php
                        $months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                                   7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
                    @endphp
                    @foreach($months as $n => $label)
                        <option value="{{ $n }}" @selected(old('reference_month', now()->month) == $n)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Ano de referência <span class="text-rose-500">*</span></label>
                <select name="reference_year" required
                        class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                    @foreach(range(now()->year, now()->year - 4) as $y)
                        <option value="{{ $y }}" @selected(old('reference_year', now()->year) == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Descrição --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Descrição <span class="text-slate-400 font-normal">(opcional)</span></label>
            <input type="text" name="description" value="{{ old('description', 'Holerite') }}" maxlength="120"
                   placeholder="Ex: Holerite, 13º Salário, PLR…"
                   class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
        </div>

        {{-- Upload PDF --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Arquivo(s) PDF <span class="text-rose-500">*</span></label>
            <div id="drop-zone"
                 class="relative border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/40 transition-all">
                <input type="file" name="files[]" id="file-input" accept=".pdf,application/pdf" multiple required
                       class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                </svg>
                <p class="text-sm font-medium text-slate-600" id="drop-label">Clique ou arraste os PDFs aqui</p>
                <p class="text-xs text-slate-400 mt-1">Máximo 10 MB por arquivo • Apenas PDF</p>
            </div>
            <div id="file-list" class="mt-2 space-y-1.5 hidden"></div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit"
                class="bg-emerald-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-emerald-700 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
            </svg>
            Enviar holerites
        </button>
        <a href="{{ route('painel.payslips.index') }}" class="text-sm text-slate-500 py-2.5 px-2">Cancelar</a>
    </div>
</form>
</div>

<script>
// Carregar colaboradores ao mudar empresa
document.getElementById('company-sel')?.addEventListener('change', function() {
    const cid = this.value;
    const sel = document.getElementById('employee-sel');
    sel.innerHTML = '';
    if (!cid) return;
    fetch(`{{ url('/painel/holerites/colaboradores') }}/${cid}`)
        .then(r => r.json())
        .then(data => {
            data.forEach(e => {
                const o = document.createElement('option');
                o.value = e.id; o.textContent = e.name;
                sel.appendChild(o);
            });
        });
});

// Preview dos arquivos selecionados
document.getElementById('file-input').addEventListener('change', function() {
    const list = document.getElementById('file-list');
    const label = document.getElementById('drop-label');
    list.innerHTML = '';
    if (this.files.length === 0) { list.classList.add('hidden'); return; }
    list.classList.remove('hidden');
    label.textContent = `${this.files.length} arquivo(s) selecionado(s)`;
    Array.from(this.files).forEach(f => {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 text-xs text-slate-600 bg-slate-50 rounded-lg px-3 py-2 border border-slate-200';
        div.innerHTML = `<svg class="w-3.5 h-3.5 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg><span class="flex-1 truncate">${f.name}</span><span class="text-slate-400">${(f.size/1024).toFixed(0)} KB</span>`;
        list.appendChild(div);
    });
});

// Drag & drop
const dz = document.getElementById('drop-zone');
['dragover','dragenter'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('border-indigo-400','bg-indigo-50/40'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('border-indigo-400','bg-indigo-50/40'); }));
dz.addEventListener('drop', ev => {
    const input = document.getElementById('file-input');
    const dt = new DataTransfer();
    Array.from(ev.dataTransfer.files).filter(f => f.type === 'application/pdf').forEach(f => dt.items.add(f));
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
});
</script>
@endsection
