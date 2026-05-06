<?php

namespace App\Http\Controllers\Web\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait NormalizesRequestTimeFields
{
    /**
     * input type="time" pode enviar HH:MM ou HH:MM:SS; date_format:H:i só aceita HH:MM.
     *
     * @param  list<string>  $fields
     */
    protected function normalizeRequestTimeFields(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $raw = $request->input($field);
            if (! is_string($raw)) {
                continue;
            }
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
                continue;
            }
            try {
                $request->merge([$field => Carbon::parse('2000-01-01 '.$raw)->format('H:i')]);
            } catch (\Throwable) {
                // mantém o valor para o validador acusar formato inválido
            }
        }
    }
}
