<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AuthOtp;
use Carbon\Carbon;
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
        $user->created_at = now();

        $this->assertNull($user->loginBlockMessage());
        $this->assertFalse($user->canBookTrips());
        $this->assertNotNull($user->bookingBlockMessage());
        $this->assertNotNull($user->pendingApprovalNotice());
        $this->assertStringContainsString((string) AuthOtp::TTL_MINUTES, (string) $user->pendingApprovalNotice());
        $this->assertTrue($user->isCustomerApprovalPending());
        $this->assertFalse($user->customerAllowsFreshRegistration());
        $this->assertFalse($user->isCustomerPendingApprovalExpired());
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
        $this->assertTrue($user->customerAllowsFreshRegistration());
    }

    public function test_expired_pending_customer_allows_fresh_registration(): void
    {
        $user = new User([
            'role'            => 'customer',
            'status'          => 'inactive',
            'approval_status' => User::APPROVAL_PENDING,
        ]);
        $user->created_at = Carbon::now()->subMinutes(AuthOtp::TTL_MINUTES + 1);

        $this->assertTrue($user->isCustomerPendingApprovalExpired());
        $this->assertTrue($user->customerAllowsFreshRegistration());
        $this->assertNotNull($user->customerApprovalDeadlineAt());
    }
}
