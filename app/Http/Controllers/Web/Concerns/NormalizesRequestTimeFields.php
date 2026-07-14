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

    /**
     * Normaliza mapas dia→horário (ex.: entry_by_day[1] = "08:00:00").
     *
     * @param  list<string>  $fields
     */
    protected function normalizeRequestTimeMapFields(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $raw = $request->input($field);
            if (! is_array($raw)) {
                continue;
            }
            $normalized = [];
            foreach ($raw as $key => $value) {
                if (! is_string($value)) {
                    $normalized[$key] = $value;

                    continue;
                }
                $value = trim($value);
                if ($value === '') {
                    $normalized[$key] = '';

                    continue;
                }
                if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
                    $normalized[$key] = $value;

                    continue;
                }
                try {
                    $normalized[$key] = Carbon::parse('2000-01-01 '.$value)->format('H:i');
                } catch (\Throwable) {
                    $normalized[$key] = $value;
                }
            }
            $request->merge([$field => $normalized]);
        }
    }
}
