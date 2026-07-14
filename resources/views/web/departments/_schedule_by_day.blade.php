{{-- Entrada/saída por dia (0=Dom .. 6=Sab). $department null = criar --}}
@php
    $defEntry = old('entry_time', $department?->entry_time
        ? \Carbon\Carbon::parse($department->entry_time)->format('H:i')
        : ($defaultEntry ?? '08:00'));
    $defExit = old('exit_time', $department?->exit_time
        ? \Carbon\Carbon::parse($department->exit_time)->format('H:i')
        : ($defaultExit ?? '18:00'));
@endphp
<div class="border border-slate-200 rounded-lg p-4 bg-slate-50/80" id="schedule-by-day-box">
    <p class="text-xs font-semibold text-slate-700 mb-1">Horário por dia da semana</p>
    <p class="text-[11px] text-slate-500 mb-3">
        Altere a <strong>entrada/saída padrão</strong> acima para atualizar todos os dias de uma vez,
        ou ajuste cada dia individualmente (ex.: sexta com saída diferente).
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',0=>'Dom'] as $d => $lab)
            @php
                $entryVal = old('entry_by_day.'.$d, $department
                    ? ($department->getEntryTimeForDay($d) ?? $defEntry)
                    : $defEntry);
                $exitVal = old('exit_by_day.'.$d, $department
                    ? ($department->getExitTimeForDay($d) ?? $defExit)
                    : $defExit);
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-2.5">
                <p class="text-[11px] font-semibold text-slate-600 mb-1.5">{{ $lab }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] text-slate-500 mb-0.5">Entrada</label>
                        <input type="time" name="entry_by_day[{{ $d }}]" value="{{ $entryVal }}" step="60"
                               data-schedule-entry
                               class="w-full text-sm border border-slate-300 rounded-lg px-2 py-1.5 bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-500 mb-0.5">Saída</label>
                        <input type="time" name="exit_by_day[{{ $d }}]" value="{{ $exitVal }}" step="60"
                               data-schedule-exit
                               class="w-full text-sm border border-slate-300 rounded-lg px-2 py-1.5 bg-white">
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<script>
(function () {
    var entryPadrao = document.querySelector('input[name="entry_time"]');
    var exitPadrao = document.querySelector('input[name="exit_time"]');
    if (entryPadrao) {
        entryPadrao.addEventListener('change', function () {
            document.querySelectorAll('[data-schedule-entry]').forEach(function (inp) {
                inp.value = entryPadrao.value;
            });
        });
    }
    if (exitPadrao) {
        exitPadrao.addEventListener('change', function () {
            document.querySelectorAll('[data-schedule-exit]').forEach(function (inp) {
                inp.value = exitPadrao.value;
            });
        });
    }
})();
</script>
