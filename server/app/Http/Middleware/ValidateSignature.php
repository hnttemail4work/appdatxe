<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSignature
{
    public function handle(Request $request, Closure $next, ...$options): Response
    {
        if ($request->hasValidSignature(true)) {
            return $next($request);
        }

        throw new BadRequestHttpException();
    }
}
