<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Mật khẩu PIN 6 chữ số — dùng chung khách / tài xế. */
class PinPassword
{
    public const LENGTH = 6;

    public static function isValid(?string $pin): bool
    {
        return is_string($pin) && (bool) preg_match('/^\d{'.self::LENGTH.'}$/', $pin);
    }

    public static function assertValid(?string $pin, string $field = 'password'): string
    {
        $pin = trim((string) $pin);
        if (! self::isValid($pin)) {
            throw ValidationException::withMessages([
                $field => AuthMessages::PIN_DIGITS,
            ]);
        }

        return $pin;
    }

    public static function hash(string $pin): string
    {
        return Hash::make(self::assertValid($pin));
    }

    public static function check(string $plain, string $hashed): bool
    {
        if (! self::isValid($plain)) {
            return false;
        }

        return Hash::check($plain, $hashed);
    }

    /** @return list<string> */
    public static function rules(bool $confirmed = false): array
    {
        $rules = ['required', 'string', 'digits:'.self::LENGTH];
        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
