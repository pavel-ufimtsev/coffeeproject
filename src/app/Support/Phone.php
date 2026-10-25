<?php

namespace App\Support;

/**
 * Russian mobile and landline numbers. Stored as +7XXXXXXXXXX, shown as
 * "+7 909 701-90-07".
 */
class Phone
{
    /**
     * "+7 (909) 701-90-07", "8 909 7019007" or "9097019007" → "+79097019007";
     * null when it is not a Russian number.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? '+7'.$digits : null;
    }

    public static function format(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return (string) $phone;
        }

        $d = substr($normalized, 2);

        return sprintf('+7 %s %s-%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
    }
}
