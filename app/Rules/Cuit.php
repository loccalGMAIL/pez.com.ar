<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an Argentine CUIT/CUIL: 11 digits (dashes optional) with a valid check digit.
 */
class Cuit implements ValidationRule
{
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('El :attribute no es un CUIT válido.');
        }
    }

    public static function normalize(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    public static function format(string $value): string
    {
        $digits = self::normalize($value);

        return strlen($digits) === 11
            ? substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10)
            : $value;
    }

    public static function isValid(string $value): bool
    {
        if (! preg_match('/^\d{2}-?\d{8}-?\d$/', trim($value))) {
            return false;
        }

        $digits = self::normalize($value);

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }

        $check = 11 - ($sum % 11);
        $check = match ($check) {
            11 => 0,
            10 => 9,
            default => $check,
        };

        return $check === (int) $digits[10];
    }
}
