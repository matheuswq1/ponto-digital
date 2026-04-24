{{-- Intervalo por dia (0=Dom .. 6=Sab). $department null = criar --}}
@php
    $def = (int) ($defaultLunch ?? 60);
@endphp
<div class="border border-slate-200 rounded-lg p-4 bg-slate-50/80" id="lunch-by-day-box">
    <p class="text-xs font-semibold text-slate-700 mb-1">Intervalo de almoço por dia</p>
    <p class="text-[11px] text-slate-500 mb-3">Altere o <strong>intervalo padrão</strong> acima para atualizar todos os dias de uma vez, ou ajuste cada dia individualmente.</p>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',0=>'Dom'] as $d => $lab)
            @php
                $val = old('lunch_by_day.'.$d, $department ? $department->getLunchMinutesForDay($d) : $def);
            @endphp
            <div>
                <label class="block text-[11px] font-medium text-slate-600 mb-0.5">{{ $lab }}</label>
                <input type="number" name="lunch_by_day[{{ $d }}]" min="0" max="300" step="1"
                       value="{{ $val }}"
                       data-lunch-day
                       class="w-full text-sm border border-slate-300 rounded-lg px-2 py-1.5 bg-white">
            </div>
        @endforeach
    </div>
</div>
<script>
(function () {
    var padrao = document.querySelector('input[name="lunch_minutes"]');
    if (!padrao) return;

    padrao.addEventListener('change', function () {
        var novoVal = parseInt(this.value, 10);
        if (isNaN(novoVal)) return;
        document.querySelectorAll('[data-lunch-day]').forEach(function (inp) {
            inp.value = novoVal;
        });
    });
})();
</script>
