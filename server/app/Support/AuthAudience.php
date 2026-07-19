<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/** Nhớ luồng đăng nhập KH vs TX qua OTP / quên PIN (tránh hard-code route login khách). */
final class AuthAudience
{
    public const SESSION_KEY = 'auth_audience';

    public const DRIVER = 'driver';

    public const CUSTOMER = 'customer';

    public static function remember(Request $request, string $audience): void
    {
        $request->session()->put(
            self::SESSION_KEY,
            $audience === self::DRIVER ? self::DRIVER : self::CUSTOMER,
        );
    }

    public static function rememberFromUser(Request $request, User $user): void
    {
        self::remember($request, $user->role === 'driver' ? self::DRIVER : self::CUSTOMER);
    }

    public static function rememberDriver(Request $request, bool $forDriver): void
    {
        self::remember($request, $forDriver ? self::DRIVER : self::CUSTOMER);
    }

    public static function isDriver(Request $request): bool
    {
        if ($request->routeIs('driver.login', 'driver.register', 'register')) {
            return true;
        }

        if ($request->boolean('for_driver')
            || $request->query('for_driver') === '1'
            || $request->query('from') === 'driver'
            || $request->input('from') === 'driver') {
            return true;
        }

        // check-phone / form login KH: không dính session TX cũ (JS TX luôn gửi for_driver=1).
        if ($request->routeIs('login', 'login.checkPhone', 'customer.register')) {
            return false;
        }

        return $request->session()->get(self::SESSION_KEY) === self::DRIVER;
    }

    public static function loginRouteName(Request $request): string
    {
        return self::isDriver($request) ? 'driver.login' : 'login';
    }

    public static function loginUrl(Request $request, array $parameters = []): string
    {
        return route(self::loginRouteName($request), $parameters);
    }

    /** Màn auth mobile: lỗi hiện 1 lần ở flash trên, không nhân đôi dưới ô nhập. */
    public static function isAuthScreen(?Request $request = null): bool
    {
        $request ??= request();

        return $request->routeIs(
            'login',
            'driver.login',
            'driver.register',
            'register',
            'customer.register',
            'auth.register.otp',
            'password.reset.request',
            'password.reset.code',
            'password.reset.pin',
            'admin.login',
        );
    }
}
