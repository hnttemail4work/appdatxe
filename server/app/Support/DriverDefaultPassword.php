<?php

namespace App\Support;

use App\Models\User;

/** Mật khẩu PIN — 6 số cuối SĐT (legacy); admin reset dùng PIN 6 số ngẫu nhiên. */
class DriverDefaultPassword
{
    public static function plainFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) >= 6) {
            return substr($digits, -6);
        }

        return str_pad($digits !== '' ? $digits : '123456', 6, '0', STR_PAD_LEFT);
    }

    public static function randomPlain(int $length = 6): string
    {
        $length = 6;
        $plain = '';

        for ($i = 0; $i < $length; $i++) {
            $plain .= (string) random_int(0, 9);
        }

        return $plain;
    }

    public static function resetToRandom(User $user, bool $mustChange = false): string
    {
        $plain = self::randomPlain(6);

        $user->update([
            'password'             => $plain,
            'must_change_password' => $mustChange,
            'login_fail_count'     => 0,
            'login_locked_until'   => null,
        ]);

        return $plain;
    }
}
