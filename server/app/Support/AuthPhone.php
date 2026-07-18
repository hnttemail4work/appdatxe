<?php

namespace App\Support;

use App\Rules\UniqueNormalizedPhone;

/** Số điện thoại VN dùng chung auth (login / đăng ký / quên MK). */
class AuthPhone
{
    public static function normalize(?string $phone): string
    {
        return AuthIdentifier::normalizePhone(trim((string) $phone));
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== '' && (bool) preg_match('/^0\d{8,10}$/', $normalized);
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string|\Closure>
     */
    public static function rules(bool $unique = false, ?int $ignoreUserId = null): array
    {
        $rules = [
            'required',
            'string',
            'max:30',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! self::isValid(is_string($value) ? $value : null)) {
                    $fail(AuthMessages::PHONE_INVALID);
                }
            },
        ];

        if ($unique) {
            $rules[] = new UniqueNormalizedPhone($ignoreUserId);
        }

        return $rules;
    }
}
