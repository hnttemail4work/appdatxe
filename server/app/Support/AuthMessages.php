<?php

namespace App\Support;

/** Message validation auth — dùng chung Request / controller. */
class AuthMessages
{
    public const PHONE_REQUIRED = 'Vui lòng nhập số điện thoại.';

    public const PHONE_INVALID = 'Số điện thoại không đúng định dạng Việt Nam.';

    public const PHONE_TAKEN = 'Số điện thoại này đã được đăng ký. Vui lòng dùng số khác hoặc đăng nhập bằng số đó.';

    public const PHONE_NOT_FOUND = 'Không tìm thấy tài khoản với số điện thoại này.';

    public const PIN_REQUIRED = 'Vui lòng nhập PIN 6 số.';

    public const PIN_DIGITS = 'PIN phải gồm đúng 6 chữ số.';

    public const PIN_CONFIRMED = 'Nhập lại PIN không khớp.';

    public const CODE_REQUIRED = 'Vui lòng nhập mã 6 số.';

    public const CODE_DIGITS = 'Mã phải gồm đúng 6 chữ số.';

    public const TERMS_ACCEPTED = 'Vui lòng đồng ý với điều khoản.';

    public const EMAIL_INVALID = 'Email không đúng định dạng.';

    /** @return array<string, string> */
    public static function pin(): array
    {
        return [
            'password.required'              => self::PIN_REQUIRED,
            'password.digits'                => self::PIN_DIGITS,
            'password.confirmed'             => self::PIN_CONFIRMED,
            'password_confirmation.required' => self::PIN_REQUIRED,
            'password_confirmation.digits'   => self::PIN_DIGITS,
        ];
    }

    /** @return array<string, string> */
    public static function phone(): array
    {
        return [
            'phone.required' => self::PHONE_REQUIRED,
        ];
    }

    /** @return array<string, string> */
    public static function code(string $field = 'code'): array
    {
        return [
            $field.'.required' => self::CODE_REQUIRED,
            $field.'.digits'   => self::CODE_DIGITS,
        ];
    }

    /** @return array<string, string> */
    public static function authCommon(): array
    {
        return array_merge(self::phone(), self::pin(), self::code(), [
            'terms.accepted' => self::TERMS_ACCEPTED,
            'email.email'    => self::EMAIL_INVALID,
        ]);
    }
}
