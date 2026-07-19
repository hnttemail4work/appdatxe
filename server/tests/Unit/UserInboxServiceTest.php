<?php

namespace Tests\Unit;

use App\Models\CustomerInboxMessage;
use App\Models\DriverInboxMessage;
use App\Models\User;
use App\Services\CustomerInboxService;
use App\Services\UserInboxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserInboxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('customer_inbox_messages');
        Schema::dropIfExists('driver_inbox_messages');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role');
            $table->string('status')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamps();
        });

        $inbox = function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('category', 20);
            $table->string('title');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        };

        Schema::create('customer_inbox_messages', $inbox);
        Schema::create('driver_inbox_messages', $inbox);
    }

    public function test_notify_registration_success_for_customer(): void
    {
        $user = User::query()->create([
            'name'            => '0909111001',
            'phone'           => '0909111001',
            'password'        => Hash::make('123456'),
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        app(UserInboxService::class)->notifyRegistrationSuccess($user);

        $message = CustomerInboxMessage::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($message);
        $this->assertSame(CustomerInboxMessage::CATEGORY_NOTICE, $message->category);
        $this->assertSame('Đăng ký', $message->title);
        $this->assertSame('registration_success', data_get($message->meta, 'type'));
        $this->assertNull($message->read_at);
        $this->assertSame(0, DriverInboxMessage::query()->count());
    }

    public function test_notify_registration_success_for_driver(): void
    {
        $user = User::query()->create([
            'name'            => '0909111002',
            'phone'           => '0909111002',
            'password'        => Hash::make('123456'),
            'role'            => 'driver',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        app(UserInboxService::class)->notifyRegistrationSuccess($user);

        $message = DriverInboxMessage::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($message);
        $this->assertSame(DriverInboxMessage::CATEGORY_NOTICE, $message->category);
        $this->assertSame('Đăng ký', $message->title);
        $this->assertSame('registration_success', data_get($message->meta, 'type'));
        $this->assertSame(0, CustomerInboxMessage::query()->count());
    }

    public function test_notify_registration_approved_rewrites_pending_message(): void
    {
        $user = User::query()->create([
            'name'            => '0909111004',
            'phone'           => '0909111004',
            'password'        => Hash::make('123456'),
            'role'            => 'customer',
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        app(UserInboxService::class)->notifyRegistrationSuccess($user);
        app(UserInboxService::class)->notifyRegistrationApproved($user);

        $message = CustomerInboxMessage::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('Duyệt hồ sơ', $message->title);
        $this->assertSame('registration_approved', data_get($message->meta, 'type'));
        $this->assertStringNotContainsString('chờ duyệt CCCD', $message->body);
        $this->assertStringContainsString('thành công', $message->body);
        $this->assertSame(1, CustomerInboxMessage::query()->where('user_id', $user->id)->count());
    }

    public function test_customer_inbox_prune_keeps_ten_per_category(): void
    {
        $user = User::query()->create([
            'name'            => '0909111003',
            'phone'           => '0909111003',
            'password'        => Hash::make('123456'),
            'role'            => 'customer',
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        $inbox = app(CustomerInboxService::class);
        for ($i = 1; $i <= 12; $i++) {
            $inbox->notify(
                (int) $user->id,
                CustomerInboxMessage::CATEGORY_NOTICE,
                'Tin '.$i,
                'Nội dung '.$i,
                ['type' => 'test', 'n' => $i],
            );
        }

        $this->assertSame(10, CustomerInboxMessage::query()->where('user_id', $user->id)->count());
        $this->assertTrue(
            CustomerInboxMessage::query()
                ->where('user_id', $user->id)
                ->where('title', 'Tin 12')
                ->exists()
        );
        $this->assertFalse(
            CustomerInboxMessage::query()
                ->where('user_id', $user->id)
                ->where('title', 'Tin 1')
                ->exists()
        );
    }
}
