<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'driver' || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('driver.password.update', 'logout', 'driver.dashboard')) {
            if ($request->routeIs('driver.dashboard') && $request->query('tab', 'account') !== 'account') {
                return redirect()->route('driver.dashboard', ['tab' => 'account'])
                    ->with('error', 'Vui lòng đổi mật khẩu trước khi tiếp tục.');
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'Vui lòng đổi mật khẩu trước khi tiếp tục.',
                'redirect' => route('driver.dashboard', ['tab' => 'account'], false),
            ], 403);
        }

        return redirect()->route('driver.dashboard', ['tab' => 'account'])
            ->with('error', 'Vui lòng đổi mật khẩu trước khi tiếp tục.');
    }
}
