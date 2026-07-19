<?php

namespace App\Http\Middleware;

use App\Support\RoleDashboard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // Admin mở form đăng nhập/đăng ký TX từ menu — không redirect về dashboard.
                if (($user->role ?? null) === 'admin' && $this->adminMayPreviewDriverAuth($request)) {
                    return $next($request);
                }

                return redirect(RoleDashboard::forUser($user, $request));
            }
        }

        $response = $next($request);

        // Tránh bfcache khi Back về /login|/register khi đã đăng nhập.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function adminMayPreviewDriverAuth(Request $request): bool
    {
        if ($request->routeIs('driver.login', 'driver.register', 'register', 'login.checkPhone')) {
            return true;
        }

        // POST /login khi đang ở luồng TX (for_driver).
        return $request->isMethod('POST')
            && $request->is('login')
            && $request->boolean('for_driver');
    }
}