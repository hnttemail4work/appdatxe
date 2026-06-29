<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dat-xe')->assertRedirect('/');
        $this->get('/login')->assertOk();
    }

    public function test_authenticated_dashboards_load(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'email' => 'op@test.test']);
        $this->actingAs($operator)->get('/operator/dashboard')->assertOk();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@test.test']);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $driver = User::factory()->create(['role' => 'driver', 'email' => 'driver@test.test']);
        $this->actingAs($driver)->get('/driver/dashboard')->assertOk();
    }
}
