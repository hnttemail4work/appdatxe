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
}
