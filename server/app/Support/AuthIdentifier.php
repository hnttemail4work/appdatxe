<?php

namespace App\Support;

use App\Models\User;

/** Định danh đăng nhập khách / tài xế theo số điện thoại. */
class AuthIdentifier
{
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            return '0' . substr($digits, 2);
        }

        return $digits;
    }

    public static function findUserByPhone(string $phone): ?User
    {
        $normalized = self::normalizePhone(trim($phone));

        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(fn (User $user): bool => self::normalizePhone((string) $user->phone) === $normalized);
    }
}
