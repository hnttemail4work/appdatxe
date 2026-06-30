<?php

namespace Tests\Unit;

use App\Models\ReferralCode;
use App\Services\ReferralCodeService;
use Tests\TestCase;

class ReferralDiscountRulesTest extends TestCase
{
    public function test_referrer_code_has_no_customer_discount(): void
    {
        $referral = new ReferralCode([
            'type'   => ReferralCode::TYPE_REFERRER,
            'status' => ReferralCode::STATUS_ACTIVE,
        ]);

        $this->assertSame(0.0, $referral->customerDiscountPercent());
        $this->assertFalse($referral->grantsCustomerDiscount());
    }

    public function test_booking_temp_code_grants_customer_discount(): void
    {
        $referral = new ReferralCode([
            'type'   => ReferralCode::TYPE_BOOKING_TEMP,
            'status' => ReferralCode::STATUS_ACTIVE,
        ]);

        $this->assertTrue($referral->grantsCustomerDiscount());
    }

    public function test_discount_meta_for_referrer_is_attribution_only_when_no_discount(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'code'   => 'GTTEST01',
            'type'   => ReferralCode::TYPE_REFERRER,
            'status' => ReferralCode::STATUS_ACTIVE,
            'customer_discount_percent' => 0,
        ]);

        $meta = $service->discountMeta($referral);

        $this->assertSame(0.0, $meta['percent']);
        $this->assertFalse($meta['eligible']);
        $this->assertTrue($meta['attribution_only']);
    }

    public function test_referrer_code_discount_applies_on_booking_page(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'code'   => 'GTTEST02',
            'type'   => ReferralCode::TYPE_REFERRER,
            'status' => ReferralCode::STATUS_ACTIVE,
            'customer_discount_percent' => 5,
        ]);

        $meta = $service->discountMeta($referral, '0909999888');

        $this->assertSame(5.0, $meta['percent']);
        $this->assertTrue($meta['eligible']);
        $this->assertFalse($meta['attribution_only']);
    }

    public function test_should_attribute_referrer_without_discount(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'type'   => ReferralCode::TYPE_REFERRER,
            'status' => ReferralCode::STATUS_ACTIVE,
        ]);

        $this->assertTrue($service->shouldAttributeBooking($referral, '0901234567'));
        $this->assertSame(0.0, $service->customerDiscountPercent($referral, '0901234567'));
    }
}
