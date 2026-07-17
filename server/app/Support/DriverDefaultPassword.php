<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Mật khẩu ban đầu cho tài xế — 6 số cuối SĐT; admin reset dùng mã 8 ký tự ngẫu nhiên. */
class DriverDefaultPassword
{
    private const RANDOM_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function plainFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) >= 6) {
            return substr($digits, -6);
        }

        return str_pad($digits !== '' ? $digits : '123456', 6, '0', STR_PAD_LEFT);
    }

    public static function randomPlain(int $length = 8): string
    {
        $length = max(6, min(16, $length));
        $chars = self::RANDOM_CHARS;
        $max = strlen($chars) - 1;
        $plain = '';

        for ($i = 0; $i < $length; $i++) {
            $plain .= $chars[random_int(0, $max)];
        }

        return $plain;
    }

    public static function resetToRandom(User $user, bool $mustChange = true): string
    {
        $plain = self::randomPlain(8);

        $user->update([
            'password'             => Hash::make($plain),
            'must_change_password' => $mustChange,
        ]);

        return $plain;
    }
}
