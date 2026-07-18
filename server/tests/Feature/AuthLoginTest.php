<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminBootstrapAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private function seedDriver(string $pin = '123456'): User
    {
        return User::query()->create([
            'name'     => 'Nguyễn Văn Tài',
            'email'    => 'driver@appdatxe.test',
            'phone'    => '0900000003',
            'password' => $pin,
            'role'     => 'driver',
            'status'   => 'active',
        ]);
    }

    public function test_driver_can_login_with_phone_and_pin(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'phone'    => '0900000003',
            'password' => '123456',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_driver_can_login_with_phone_country_code(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'phone'    => '+84900000003',
            'password' => '123456',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_pin(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'phone'    => '0900000003',
            'password' => '000000',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_driver_cannot_login_with_email(): void
    {
        $this->seedDriver();

        $response = $this->post('/login', [
            'phone'    => 'driver@appdatxe.test',
            'password' => '123456',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_inactive_driver_cannot_login(): void
    {
        User::query()->create([
            'name'     => 'Inactive Driver',
            'email'    => 'inactive@test.test',
            'phone'    => '0900000099',
            'password' => '123456',
            'role'     => 'driver',
            'status'   => 'inactive',
        ]);

        $response = $this->post('/login', [
            'phone'    => '0900000099',
            'password' => '123456',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_admin_can_login_on_admin_route(): void
    {
        AdminBootstrapAccount::ensure();

        $response = $this->post(route('admin.login'), [
            'login'    => AdminBootstrapAccount::LOGIN,
            'password' => AdminBootstrapAccount::PASSWORD_PLAIN,
        ]);

        $response->assertRedirect(route('admin.bookings'));
        $this->assertAuthenticated();
        $this->assertSame('admin', auth()->user()->role);
        $this->assertSame(AdminBootstrapAccount::LOGIN, auth()->user()->email);
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        AdminBootstrapAccount::ensure();

        $response = $this->post(route('admin.login'), [
            'login'    => AdminBootstrapAccount::LOGIN,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
