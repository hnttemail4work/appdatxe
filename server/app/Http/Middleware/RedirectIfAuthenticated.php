<?php

namespace App\Http\Middleware;

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
                $role = Auth::guard($guard)->user()->role;
                $redirect = match ($role) {
                    'admin'    => route('admin.dashboard'),
                    'operator' => route('operator.dashboard'),
                    'driver'   => route('driver.dashboard'),
                    default    => route('customer.dashboard'),
                };
                return redirect($redirect);
            }
        }

        return $next($request);
    }
}
