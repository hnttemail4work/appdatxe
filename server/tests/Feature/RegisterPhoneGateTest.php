<?php

namespace Tests\Feature;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chặn sớm SĐT khi đăng ký — cần MySQL (legacy migrations không chạy trên SQLite).
 */
class RegisterPhoneGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
        if ($connection === 'sqlite') {
            $this->markTestSkipped('Register phone gate feature tests require MySQL/MariaDB.');
        }

        parent::setUp();
    }

    public function test_check_phone_active_customer_returns_login_url(): void
    {
        User::query()->create([
            'name'                 => 'Khách Active',
            'email'                => null,
            'phone'                => '0901111222',
            'password'             => '123456',
            'role'                 => 'customer',
            'status'               => 'active',
            'approval_status'      => User::APPROVAL_APPROVED,
            'register_otp_verified_at' => now(),
        ]);

        $response = $this->postJson(route('login.checkPhone'), [
            'phone' => '0901111222',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('login_url', route('login', ['phone' => '0901111222']));
    }

    public function test_check_phone_suspended_returns_inactive_locked_message(): void
    {
        User::query()->create([
            'name'            => 'Khách khóa',
            'email'           => null,
            'phone'           => '0901111333',
            'password'        => '123456',
            'role'            => 'customer',
            'status'          => 'suspended',
            'approval_status' => User::APPROVAL_APPROVED,
            'register_otp_verified_at' => now(),
        ]);

        $response = $this->postJson(route('login.checkPhone'), [
            'phone' => '0901111333',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('message', 'Tài khoản đang bị khóa.');
    }

    public function test_check_phone_pending_customer_returns_needs_otp(): void
    {
        User::query()->create([
            'name'            => 'Khách chờ',
            'email'           => null,
            'phone'           => '0901111444',
            'password'        => '123456',
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $response = $this->postJson(route('login.checkPhone'), [
            'phone' => '0901111444',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'needs_otp')
            ->assertJsonStructure(['otp_url']);
    }

    public function test_register_customer_with_existing_active_phone_redirects_to_login(): void
    {
        Storage::fake('public');

        User::query()->create([
            'name'                 => 'Khách Active',
            'email'                => null,
            'phone'                => '0901111555',
            'password'             => '123456',
            'role'                 => 'customer',
            'status'               => 'active',
            'approval_status'      => User::APPROVAL_APPROVED,
            'register_otp_verified_at' => now(),
        ]);

        $response = $this->post(route('customer.register'), [
            'phone'                 => '0901111555',
            'password'              => '135790',
            'password_confirmation' => '135790',
            'photo_id_card'         => UploadedFile::fake()->image('front.jpg'),
            'photo_id_card_back'    => UploadedFile::fake()->image('back.jpg'),
            'terms'                 => '1',
        ]);

        $response->assertRedirect(route('login', ['phone' => '0901111555']));
        $this->assertSame(1, User::query()->where('phone', '0901111555')->count());
    }

    public function test_check_phone_active_driver_returns_driver_login_url(): void
    {
        $user = User::query()->create([
            'name'                 => 'TX Active',
            'email'                => null,
            'phone'                => '0902222111',
            'password'             => '123456',
            'role'                 => 'driver',
            'status'               => 'active',
            'register_otp_verified_at' => now(),
        ]);

        DriverProfile::query()->create([
            'user_id'          => $user->id,
            'approval_status'  => 'approved',
            'availability_status' => 'off_duty',
        ]);

        $response = $this->postJson(route('login.checkPhone'), [
            'phone'      => '0902222111',
            'for_driver' => '1',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('login_url', route('driver.login', ['phone' => '0902222111']));
    }
}
