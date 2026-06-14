<?php

namespace App\Http\Middleware;

use App\Support\RoleDashboard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active') {
            abort(403, 'Forbidden.');
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect(RoleDashboard::route($user->role))
                ->with('error', 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
