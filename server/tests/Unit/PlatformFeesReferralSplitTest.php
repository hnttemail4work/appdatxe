<?php

namespace Tests\Unit;

use App\Models\ReferralCode;
use Tests\TestCase;

class PlatformFeesReferralSplitTest extends TestCase
{
    public function test_referrer_code_uses_commission_not_customer_discount_by_default(): void
    {
        $referral = new ReferralCode([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'commission_percent'        => 8,
            'customer_discount_percent' => 0,
        ]);

        $this->assertSame(8.0, $referral->commissionPercent());
        $this->assertSame(0.0, $referral->customerDiscountPercent());
        $this->assertFalse($referral->grantsCustomerDiscount());
    }

    public function test_driver_invite_code_uses_stored_customer_discount(): void
    {
        $referral = new ReferralCode([
            'type'                      => ReferralCode::TYPE_REFERRER,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'driver_profile_id'         => 1,
            'customer_discount_percent' => 2,
        ]);

        $this->assertTrue($referral->isDriverInvite());
        $this->assertSame(2.0, $referral->customerDiscountPercent());
        $this->assertSame(0.0, $referral->commissionPercent());
        $this->assertTrue($referral->grantsCustomerDiscount());
    }
}
