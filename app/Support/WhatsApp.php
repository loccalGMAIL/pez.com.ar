<?php

namespace App\Support;

class WhatsApp
{
    /**
     * Best-effort conversion of an Argentine phone to the international format
     * wa.me expects (549 + area code + number). Returns null when the number
     * can't be trusted, so WhatsApp lets the user pick the contact instead.
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '549') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '54') && strlen($digits) === 12) {
            return '549'.substr($digits, 2);
        }

        $digits = ltrim($digits, '0');

        // Local mobile written with the "15" prefix (e.g. 011 15-1234-5678) can't be
        // split reliably without knowing the area code length.
        return strlen($digits) === 10 ? '549'.$digits : null;
    }

    public static function url(?string $phone, string $text): string
    {
        return 'https://wa.me/'.(self::phone($phone) ?? '').'?text='.rawurlencode($text);
    }
}
