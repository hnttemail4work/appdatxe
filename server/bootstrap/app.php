<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Phiên đã hết hạn. Tải lại trang và thử lại.'], 419);
            }

            if ($request->user() && $request->is('admin/*')) {
                $route = $request->routeIs('admin.referrers.*', 'admin.referralCodes.*')
                    ? 'admin.referrals'
                    : 'admin.dashboard';
                $params = ['tab' => 'settings'];
                if ($route === 'admin.referrals') {
                    $params = [];
                }
                if ($request->routeIs('admin.referrers.store')) {
                    $params['referrals_page'] = 1;
                }
                if ($request->routeIs('admin.password.update')) {
                    $params = ['tab' => 'account'];
                }

                return redirect()
                    ->route($route, $params)
                    ->withInput()
                    ->withErrors([
                        'csrf' => 'Phiên đã hết hạn. Tải lại trang (F5) rồi thử tạo mã lại.',
                    ]);
            }

            return redirect()
                ->to('/login')
                ->withErrors(['login' => 'Phiên đã hết hạn hoặc cookie bị chặn. Tải lại trang và đăng nhập lại (dùng cùng một địa chỉ: 127.0.0.1 hoặc localhost).']);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('schedules:sync')->everyMinute();
    })
    ->create();
