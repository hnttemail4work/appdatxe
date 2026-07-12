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
                if ($user->role === 'customer' && ! $request->session()->get('customer_biometric_verified')) {
                    return redirect()->route('auth.biometric');
                }

                return redirect(RoleDashboard::route($user->role));
            }
        }

        return $next($request);
    }
}
