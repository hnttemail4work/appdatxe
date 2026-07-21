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

    public function test_same_phone_as_referrer_blocks_discount(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'code'                      => 'GTSELF01',
            'type'                      => ReferralCode::TYPE_REFERRER,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'phone'                     => '0901234567',
            'customer_discount_percent' => 2,
        ]);

        $meta = $service->discountMeta($referral, '0901234567');

        $this->assertSame(0.0, $meta['percent']);
        $this->assertFalse($meta['eligible']);
        $this->assertStringContainsString('trùng', (string) $meta['reason']);
        $this->assertTrue($service->isReferrerPhone($referral, '0901234567'));
        $this->assertFalse($service->shouldAttributeBooking($referral, '0901234567'));
    }

    public function test_same_phone_normalized_blocks_discount(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'code'                      => 'GTSELF02',
            'type'                      => ReferralCode::TYPE_REFERRER,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'phone'                     => '+84901234567',
            'customer_discount_percent' => 2,
        ]);

        $this->assertTrue($service->phonesMatch('0901234567', '84901234567'));
        $this->assertTrue($service->isReferrerPhone($referral, '090 123 4567'));
        $meta = $service->discountMeta($referral, '0901234567');
        $this->assertFalse($meta['eligible']);
    }

    public function test_different_phone_still_eligible_when_discount_configured(): void
    {
        $service = app(ReferralCodeService::class);
        $referral = new ReferralCode([
            'code'                      => 'GTFRIEND',
            'type'                      => ReferralCode::TYPE_REFERRER,
            'status'                    => ReferralCode::STATUS_ACTIVE,
            'phone'                     => '0901111111',
            'customer_discount_percent' => 2,
        ]);

        $this->assertFalse($service->isReferrerPhone($referral, '0909999888'));
        $this->assertSame(2.0, $referral->customerDiscountPercent());
    }
}
