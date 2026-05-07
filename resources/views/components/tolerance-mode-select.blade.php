@props([
    'name' => 'tolerance_mode',
    'value' => null,
    'inherit' => false,
    'inheritLabel' => 'Herdar da empresa',
    'hint' => null,
])

<select name="{{ $name }}" {{ $attributes->merge(['class' => 'w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white']) }}>
    @if($inherit)
        <option value="" @selected(old($name, $value) === '' || old($name, $value) === null)>{{ $inheritLabel }}</option>
    @endif
    <option value="daily_dead_band" @selected(old($name, $value) === 'daily_dead_band')>Faixa neutra (dead band)</option>
    <option value="daily_discount" @selected(old($name, $value) === 'daily_discount')>Desconto no saldo diário</option>
    <option value="clt_event_based" @selected(old($name, $value) === 'clt_event_based')>CLT por batida (5+10) — gabarito fixo</option>
    <option value="clt_event_strict" @selected(old($name, $value) === 'clt_event_strict')>CLT por batida (5+10) — retorno almoço por duração</option>
    <option value="clt_event_progressive_cap" @selected(old($name, $value) === 'clt_event_progressive_cap')>CLT por batida — bucket progressivo (até 5 no bucket; 6–9 divide; ≥10 ou |bucket|≥10 libera)</option>
</select>
@if($hint)
    <p class="text-[11px] text-slate-400 mt-1">{{ $hint }}</p>
@endif
