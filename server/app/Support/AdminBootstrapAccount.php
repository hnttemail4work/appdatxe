<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Tài khoản quản trị mặc định khi deploy / reset dữ liệu live. */
class AdminBootstrapAccount
{
    public const LOGIN = 'gozvietadmin';

    public const PASSWORD_PLAIN = '2026g0zv!3tm@n@g3r';

    public const NAME = 'Admin';

    public static function ensure(): User
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->where('email', self::LOGIN)
            ->first()
            ?? User::query()->where('role', 'admin')->orderBy('id')->first();

        $payload = [
            'email'                => self::LOGIN,
            'name'                 => self::NAME,
            'password'             => self::PASSWORD_PLAIN,
            'phone'                => null,
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ];

        if ($admin) {
            $admin->update($payload);

            return $admin->fresh();
        }

        return User::query()->create($payload);
    }

    /** Đăng nhập admin: tài khoản + mật khẩu, không OTP / PIN / email verify. */
    public static function attempt(string $login, string $password): ?User
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return null;
        }

        $user = User::query()
            ->where('role', 'admin')
            ->where('email', $login)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
