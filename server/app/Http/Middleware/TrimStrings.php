<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class TrimStrings
{
    protected $except = [
        'password',
        'password_confirmation',
    ];

    public function handle(Request $request, $next)
    {
        return $next($request);
    }
}
