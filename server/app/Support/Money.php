<?php

namespace App\Support;

/** Định dạng số tiền VND thống nhất — tránh lặp number_format(...) rải rác. */
class Money
{
    /** 1234000 → "1.234.000" */
    public static function format(float|int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    /** 1234000 → "1.234.000 đ" */
    public static function vnd(float|int $amount): string
    {
        return self::format($amount) . ' đ';
    }
}
