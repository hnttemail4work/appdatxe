<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverPasswordChanged
{
    /** @var list<string> */
    private const ALLOWED_TABS = ['account', 'account-password'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'driver' || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('driver.password.update', 'logout', 'driver.dashboard')) {
            if ($request->routeIs('driver.dashboard')) {
                $tab = (string) $request->query('tab', 'account-password');
                if (! in_array($tab, self::ALLOWED_TABS, true)) {
                    return redirect()->route('driver.dashboard', ['tab' => 'account-password'])
                        ->with('error', 'Vui lòng đổi mật khẩu trước khi tiếp tục.');
                }
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'Vui lòng đổi mật khẩu trước khi tiếp tục.',
                'redirect' => route('driver.dashboard', ['tab' => 'account-password'], false),
            ], 403);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'account-password'])
            ->with('error', 'Vui lòng đổi mật khẩu trước khi tiếp tục.');
    }
}
