<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class CustomerBookingGateTest extends TestCase
{
    public function test_pending_customer_can_login_but_not_book(): void
    {
        $user = new User([
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $this->assertNull($user->loginBlockMessage());
        $this->assertFalse($user->canBookTrips());
        $this->assertNotNull($user->bookingBlockMessage());
    }

    public function test_approved_active_customer_can_book(): void
    {
        $user = new User([
            'role'            => 'customer',
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        $this->assertNull($user->loginBlockMessage());
        $this->assertTrue($user->canBookTrips());
        $this->assertNull($user->bookingBlockMessage());
    }

    public function test_rejected_customer_cannot_login(): void
    {
        $user = new User([
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_REJECTED,
        ]);

        $this->assertNotNull($user->loginBlockMessage());
        $this->assertFalse($user->canBookTrips());
    }
}
