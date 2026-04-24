{{--
  Componente de input de data em formato brasileiro (dd/mm/aaaa).
  
  Props:
    $name       — name do campo (obrigatório)
    $value      — valor em formato ISO 'Y-m-d' (o que vem do servidor)
    $label      — label opcional (se passado, renderiza o <label>)
    $class      — classes CSS extra para o input
    $required   — se o campo é obrigatório
    $id         — id do input (gerado automaticamente se não passado)
--}}
@php
    $fieldId   = $id ?? 'dateinput_' . str_replace(['[',']','.'], '_', $name);
    $fieldCls  = $class ?? 'text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none bg-white';
    // Converter valor ISO para dd/mm/aaaa para apresentação
    $display   = '';
    if (!empty($value)) {
        try {
            $display = \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            $display = $value;
        }
    }
@endphp

@if(!empty($label))
<label for="{{ $fieldId }}" class="block text-xs font-medium text-slate-600 mb-1">
    {!! $label !!}
</label>
@endif

{{-- Input visível ao utilizador (dd/mm/aaaa) --}}
<input
    type="text"
    id="{{ $fieldId }}"
    data-datebr
    placeholder="dd/mm/aaaa"
    value="{{ $display }}"
    maxlength="10"
    autocomplete="off"
    {{ isset($required) && $required ? 'required' : '' }}
    class="{{ $fieldCls }}"
    style="min-width:120px;"
>
{{-- Campo hidden que envia o valor ISO para o servidor --}}
<input type="hidden" name="{{ $name }}" id="{{ $fieldId }}_iso" value="{{ $value ?? '' }}">
