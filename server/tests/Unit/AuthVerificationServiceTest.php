<?php

namespace Tests\Unit;

use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Services\AuthVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Skip before RefreshDatabase migrates (SQLite cannot run legacy MySQL MODIFY).
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
        if ($connection === 'sqlite') {
            $this->markTestSkipped('Auth verification tests require MySQL/MariaDB (legacy MODIFY migrations).');
        }

        parent::setUp();

        if (! Schema::hasTable('auth_verification_codes')) {
            $this->markTestSkipped('auth_verification_codes table missing (migrate first).');
        }
    }

    public function test_issue_and_verify_register_otp(): void
    {
        $service = app(AuthVerificationService::class);
        $issued = $service->issue(
            '0909111222',
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            \App\Support\AuthOtp::TTL_MINUTES,
        );

        $this->assertMatchesRegularExpression('/^\d{6}$/', $issued['plain']);
        $this->assertSame($issued['plain'], data_get($issued['code']->meta, 'admin_display_code'));

        $verified = $service->verify(
            '0909111222',
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            $issued['plain'],
        );

        $this->assertSame(AuthVerificationCode::STATUS_CONSUMED, $verified->status);
        $this->assertFalse(
            AuthVerificationCode::query()->whereKey($issued['code']->id)->exists(),
            'OTP đã dùng phải bị xóa khỏi DB'
        );
    }

    public function test_wrong_otp_increments_attempts(): void
    {
        $service = app(AuthVerificationService::class);
        $issued = $service->issue(
            '0909333444',
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            \App\Support\AuthOtp::TTL_MINUTES,
        );

        try {
            $service->verify('0909333444', AuthVerificationCode::PURPOSE_REGISTER_OTP, '000000');
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // expected
        }

        $issued['code']->refresh();
        $this->assertSame(1, (int) $issued['code']->attempts);
    }

    public function test_password_reset_request_then_admin_issue(): void
    {
        if (! Schema::hasTable('users')) {
            $this->markTestSkipped('users table missing.');
        }

        $user = User::query()->create([
            'name'            => '0909555666',
            'phone'           => '0909555666',
            'password'        => Hash::make('123456'),
            'role'            => 'customer',
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        $service = app(AuthVerificationService::class);
        $request = $service->requestPasswordReset('0909555666');
        $this->assertSame(AuthVerificationCode::STATUS_PENDING_ADMIN, $request->status);

        $issued = $service->adminIssuePasswordReset($request->fresh());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $issued['plain']);
        $this->assertSame(AuthVerificationCode::PURPOSE_PASSWORD_RESET, $issued['code']->purpose);

        $service->verify('0909555666', AuthVerificationCode::PURPOSE_PASSWORD_RESET, $issued['plain']);
        $this->assertSame($user->id, $issued['code']->user_id);
    }
}
