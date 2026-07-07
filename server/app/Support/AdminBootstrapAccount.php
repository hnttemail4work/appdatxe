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
            ->where('email', self::LOGIN)
            ->orWhere('email', 'admin@appdatxe.test')
            ->orWhere('role', 'admin')
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->first();

        $payload = [
            'email'                => self::LOGIN,
            'name'                 => self::NAME,
            'password'             => Hash::make(self::PASSWORD_PLAIN),
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
}
