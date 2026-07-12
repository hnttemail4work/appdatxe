<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerBiometricVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'customer' && ! $request->session()->get('customer_biometric_verified')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => 'Vui lòng xác thực sinh trắc học để tiếp tục.',
                    'redirect' => route('auth.biometric', [], false),
                ], 403);
            }

            return redirect()->route('auth.biometric');
        }

        return $next($request);
    }
}
