<?php

namespace App\Support;

class RoleDashboard
{
    public static function route(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.bookings'),
            'driver'   => route('driver.dashboard'),
            'customer' => route('customer.account'),
            default    => route('home'),
        };
    }

    public static function urlAllowedForRole(string $url, string $role): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        if (in_array($path, ['/', '/dashboard', '/trips/search'], true)) {
            return true;
        }

        return match ($role) {
            'admin' => str_starts_with($path, '/admin'),
            'driver'   => str_starts_with($path, '/driver'),
            'customer' => $path === '/' || str_starts_with($path, '/chuyen') || str_starts_with($path, '/tai-khoan'),
            default    => $path === '/' || str_starts_with($path, '/dat-xe'),
        };
    }
}
