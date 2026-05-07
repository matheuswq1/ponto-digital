<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            $fail('CPF inválido.');

            return;
        }

        $digits = Cpf::onlyDigits($value);
        if (strlen($digits) !== 11) {
            $fail('O CPF deve ter 11 dígitos.');

            return;
        }

        if (! Cpf::isValidChecksum($digits)) {
            $fail('CPF inválido (dígitos verificadores).');
        }
    }
}
