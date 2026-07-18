<?php

namespace Tests\Feature;

use App\Models\AuthVerificationCode;
use App\Models\CustomerProfileChangeRequest;
use App\Models\User;
use App\Services\CustomerDocumentService;
use App\Services\CustomerProfileChangeService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integration tests — cần migrate đầy đủ.
 * Môi trường phpunit hiện dùng SQLite; một số migration cũ chỉ hỗ trợ MySQL nên
 * các test này sẽ skip trên SQLite và chạy khi DB testing là MySQL/MariaDB.
 */
class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
        if ($connection === 'sqlite') {
            $this->markTestSkipped('Customer registration feature tests require MySQL/MariaDB (legacy MODIFY migrations).');
        }

        parent::setUp();
    }

    private function fakeIdCard(string $name = 'cccd.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    public function test_customer_can_register_with_pin_and_gets_otp_step(): void
    {
        Storage::fake('public');

        $response = $this->post(route('customer.register'), [
            'phone'                 => '0909123456',
            'password'              => '135790',
            'password_confirmation' => '135790',
            'photo_id_card'         => $this->fakeIdCard('front.jpg'),
            'photo_id_card_back'    => $this->fakeIdCard('back.jpg'),
            'terms'                 => '1',
        ]);

        $response->assertRedirect(route('auth.register.otp'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('pending_register_otp.user_id');

        $user = User::query()->where('phone', '0909123456')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame('inactive', $user->status);
        $this->assertSame(User::APPROVAL_PENDING, $user->approval_status);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('135790', $user->password));
        $this->assertNotEmpty($user->photo_id_card);

        $this->assertTrue(
            AuthVerificationCode::query()
                ->where('phone', '0909123456')
                ->where('purpose', AuthVerificationCode::PURPOSE_REGISTER_OTP)
                ->where('status', AuthVerificationCode::STATUS_ACTIVE)
                ->exists()
        );
    }

    public function test_register_customer_service_stores_documents_and_issues_otp(): void
    {
        Storage::fake('public');

        $request = Request::create('/dang-ky', 'POST', [
            'phone'                 => '0909123460',
            'password'              => '246810',
            'password_confirmation' => '246810',
            'terms'                 => '1',
        ], [], [
            'photo_id_card'      => $this->fakeIdCard('front.jpg'),
            'photo_id_card_back' => $this->fakeIdCard('back.jpg'),
        ]);

        $result = app(RegistrationService::class)->registerCustomer([
            'phone'                 => '0909123460',
            'password'              => '246810',
            'password_confirmation' => '246810',
            'terms'                 => '1',
        ], $request);

        $user = $result['user'];
        $this->assertSame(User::APPROVAL_PENDING, $user->approval_status);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['otp_plain']);
        Storage::disk('public')->assertExists($user->photo_id_card);
        $this->assertSame(
            ['photo_id_card', 'photo_id_card_back'],
            CustomerDocumentService::idCardFields()
        );
    }

    public function test_profile_change_service_queues_admin_approval(): void
    {
        Storage::fake('public');

        $customer = User::query()->create([
            'name'            => '0909123459',
            'phone'           => '0909123459',
            'password'        => Hash::make('123456'),
            'role'            => 'customer',
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        $admin = User::query()->create([
            'name'     => 'Admin',
            'email'    => 'admin2@appdatxe.test',
            'phone'    => '0909222222',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $request = Request::create('/tai-khoan/cap-nhat', 'POST', [
            'name'   => 'Nguyễn Văn A',
            'gender' => 'male',
            'email'  => 'a@example.com',
        ]);

        $change = app(CustomerProfileChangeService::class)->submit($customer, $request);
        $this->assertTrue($change->isPending());
        $this->assertSame('Nguyễn Văn A', $change->payload['name'] ?? null);

        $customer->refresh();
        $this->assertSame('0909123459', $customer->name);

        app(CustomerProfileChangeService::class)->approve($change, $admin);
        $customer->refresh();
        $this->assertSame('Nguyễn Văn A', $customer->name);
        $this->assertSame(CustomerProfileChangeRequest::STATUS_APPROVED, $change->fresh()->status);
    }
}
