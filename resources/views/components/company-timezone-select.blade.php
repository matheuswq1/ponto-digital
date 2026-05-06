@props([
    'name' => 'timezone',
    'value' => null,
])

@php
    $appTz = config('app.timezone', 'America/Sao_Paulo');
    $choices = [
        '' => 'Padrão da aplicação ('.$appTz.')',
        'America/Noronha' => 'Fernando de Noronha',
        'America/Sao_Paulo' => 'Brasília — São Paulo',
        'America/Bahia' => 'Salvador / Bahia',
        'America/Fortaleza' => 'Fortaleza',
        'America/Recife' => 'Recife',
        'America/Maceio' => 'Maceió',
        'America/Belem' => 'Belém',
        'America/Manaus' => 'Manaus',
        'America/Rio_Branco' => 'Rio Branco',
        'America/Cuiaba' => 'Cuiabá',
        'America/Campo_Grande' => 'Campo Grande',
    ];
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => 'w-full text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white']) }}>
    @foreach($choices as $tzVal => $label)
        <option value="{{ $tzVal }}" @selected(old($name, $value ?? '') === $tzVal)>{{ $label }}</option>
    @endforeach
</select>
<p class="text-[11px] text-slate-400 mt-1">
    Usado para dia da semana em jornada, feriados relativos ao calendário da empresa e alertas de atraso.
    <span class="text-slate-500">Lista curta — para outros fusos use a API ou migração manual.</span>
</p>
