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
</select>
@if($hint)
    <p class="text-[11px] text-slate-400 mt-1">{{ $hint }}</p>
@endif
