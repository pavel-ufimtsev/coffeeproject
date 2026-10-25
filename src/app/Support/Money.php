<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in rubles the Russian way: "1 299 ₽".
     */
    public static function rub(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $amount = (float) $amount;
        $decimals = floor($amount) == $amount ? 0 : 2;

        return number_format($amount, $decimals, ',', "\u{202F}")."\u{00A0}₽";
    }
}
