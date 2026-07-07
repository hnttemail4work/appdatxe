<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\ReferralCode;
use App\Services\GuestTripStatusService;
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

    public function test_booking_temp_code_has_no_commission(): void
    {
        $referral = new ReferralCode([
            'type'   => ReferralCode::TYPE_BOOKING_TEMP,
            'status' => ReferralCode::STATUS_ACTIVE,
        ]);

        $this->assertSame(0.0, $referral->commissionPercent());
    }

    public function test_guest_referral_payload_is_pending_before_trip_complete(): void
    {
        $pendingReferral = new ReferralCode([
            'code'   => 'GTBOOK01',
            'type'   => ReferralCode::TYPE_BOOKING_TEMP,
            'status' => ReferralCode::STATUS_PENDING,
        ]);

        $booking = new Booking(['booking_reference' => 'REF-QR-1']);
        $booking->setRelation('referralCode', $pendingReferral);

        $service = app(GuestTripStatusService::class);
        $method = new \ReflectionMethod($service, 'serializeReferral');
        $method->setAccessible(true);

        $pendingPayload = $method->invoke($service, $booking);
        $this->assertTrue($pendingPayload['pending']);
        $this->assertSame(0.0, $pendingPayload['discount_percent']);
    }
}
