<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CPF brasileiro: só dígitos, máscara e validação dos DV (MESMA lógica do {@see \Database\Factories\EmployeeFactory}).
 */
final class Cpf
{
    public static function onlyDigits(?string $input): string
    {
        return preg_replace('/\D/', '', (string) $input);
    }

    /** @return non-empty-string|null Formato 000.000.000-00 quando há exatamente 11 dígitos. */
    public static function formatMasked(?string $input): ?string
    {
        $d = self::onlyDigits($input);
        if (strlen($d) !== 11) {
            return null;
        }

        return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9, 2));
    }

    /**
     * @param  non-empty-string  $digits11  apenas números, tamanho 11
     */
    public static function isValidChecksum(string $digits11): bool
    {
        if (strlen($digits11) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $digits11)) {
            return false;
        }

        $nums = array_map(intval(...), str_split(substr($digits11, 0, 9)));
        $d1 = self::checkDigit($nums, 10);
        $d2 = self::checkDigit(array_merge($nums, [$d1]), 11);

        return $d1 === (int) $digits11[9] && $d2 === (int) $digits11[10];
    }

    /** Aceita valor mascarado ou só dígitos. */
    public static function isValid(?string $input): bool
    {
        $d = self::onlyDigits((string) $input);

        return self::isValidChecksum($d);
    }

    /**
     * @param  list<int>  $nums
     */
    private static function checkDigit(array $nums, int $startWeight): int
    {
        $sum = 0;
        $w = $startWeight;
        foreach ($nums as $n) {
            $sum += $n * $w--;
        }
        $rem = $sum % 11;

        return $rem < 2 ? 0 : 11 - $rem;
    }
}
