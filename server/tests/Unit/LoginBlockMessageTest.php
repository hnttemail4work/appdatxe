<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class LoginBlockMessageTest extends TestCase
{
    public function test_suspended_user_gets_locked_message(): void
    {
        $user = new User([
            'role'   => 'customer',
            'status' => 'suspended',
        ]);

        $this->assertSame('Tài khoản đang bị khóa.', $user->loginBlockMessage());
    }

    public function test_inactive_approved_customer_gets_locked_message(): void
    {
        $user = new User([
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        $this->assertSame('Tài khoản đang bị khóa.', $user->loginBlockMessage());
    }
}
