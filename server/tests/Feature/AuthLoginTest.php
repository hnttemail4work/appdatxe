<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private function seedDriver(): User
    {
        return User::query()->create([
            'name'     => 'Nguyễn Văn Tài',
            'email'    => 'driver@appdatxe.test',
            'phone'    => '0900000003',
            'password' => Hash::make('password'),
            'role'     => 'driver',
            'status'   => 'active',
        ]);
    }

    public function test_driver_can_login_with_email(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'login'    => 'driver@appdatxe.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertAuthenticatedAs(User::query()->where('email', 'driver@appdatxe.test')->first());
    }

    public function test_driver_can_login_with_phone(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'login'    => '0900000003',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_driver_can_login_with_phone_country_code(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'login'    => '+84900000003',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'login'    => '0900000003',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_inactive_driver_cannot_login(): void
    {
        User::query()->create([
            'name'     => 'Inactive Driver',
            'email'    => 'inactive@test.test',
            'phone'    => '0900000099',
            'password' => Hash::make('password'),
            'role'     => 'driver',
            'status'   => 'inactive',
        ]);

        $response = $this->post('/login', [
            'login'    => '0900000099',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
