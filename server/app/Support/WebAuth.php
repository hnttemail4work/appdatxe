<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class WebAuth
{
    public static function attempt(string $phone, string $password): ?User
    {
        $user = AuthIdentifier::findUserByPhone($phone);

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}
