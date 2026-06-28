<?php

namespace App\Support;

use App\Models\User;

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

    public static function looksLikeEmail(string $value): bool
    {
        return str_contains(trim($value), '@');
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

    public static function findUserByLogin(string $login): ?User
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (self::looksLikeEmail($login)) {
            return User::query()->where('email', $login)->first();
        }

        return self::findUserByPhone($login);
    }
}
