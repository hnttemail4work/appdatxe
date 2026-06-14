<?php

namespace App\Support;

class RoleDashboard
{
    public static function route(string $role): string
    {
        return match ($role) {
            'admin'    => route('admin.dashboard'),
            'operator' => route('operator.dashboard'),
            'driver'   => route('driver.dashboard'),
            default    => route('customer.dashboard'),
        };
    }

    public static function urlAllowedForRole(string $url, string $role): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        if (in_array($path, ['/', '/dashboard', '/trips/search'], true)) {
            return true;
        }

        return match ($role) {
            'admin'    => str_starts_with($path, '/admin'),
            'operator' => str_starts_with($path, '/operator'),
            'driver'   => str_starts_with($path, '/driver'),
            'customer' => str_starts_with($path, '/customer'),
            default    => false,
        };
    }
}
