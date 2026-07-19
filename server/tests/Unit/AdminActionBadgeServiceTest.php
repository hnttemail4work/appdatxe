<?php

namespace Tests\Unit;

use App\Services\AdminActionBadgeService;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminActionBadgeServiceTest extends TestCase
{
    public function test_users_badge_hidden_on_pending_list(): void
    {
        $service = new AdminActionBadgeService();

        $this->app->instance('request', Request::create('/admin/users', 'GET', ['status' => 'pending']));
        request()->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], 'admin/users', []);
            $route->name('admin.users');

            return $route;
        });

        $this->assertFalse($service->usersBadgeVisible());
    }

    public function test_users_badge_visible_outside_users_section(): void
    {
        $service = new AdminActionBadgeService();

        $this->app->instance('request', Request::create('/admin/bookings', 'GET'));
        request()->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], 'admin/bookings', []);
            $route->name('admin.bookings');

            return $route;
        });

        $this->assertTrue($service->usersBadgeVisible());
    }
}
