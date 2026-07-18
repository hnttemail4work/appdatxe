<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class WebAuth
{
    /** Đăng nhập khách / tài xế — SĐT + PIN/mật khẩu. */
    public static function attemptPhone(string $phone, string $password): ?User
    {
        $user = AuthIdentifier::findUserByPhone($phone);

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
