<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\ReferralCode;
use App\Support\WalletTripFeeSplit;
use Mockery;
use Tests\TestCase;

class WalletTripFeeSplitTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_standard_split_is_90_driver_10_admin(): void
    {
        $booking = $this->bookingStub(1_000_000, discountPercent: 0, discountAmount: 0, referrer: null);
        $split = WalletTripFeeSplit::forBooking($booking);

        $this->assertSame('standard', $split['case']);
        $this->assertSame(900_000, $split['driver_amount']);
        $this->assertSame(100_000, $split['admin_amount']);
        $this->assertSame(0, $split['referrer_amount']);
        $this->assertSame(10, $split['admin_percent']);
    }

    public function test_promo_split_is_90_driver_8_admin(): void
    {
        $booking = $this->bookingStub(1_000_000, discountPercent: 2, discountAmount: 20_000, referrer: null);
        $split = WalletTripFeeSplit::forBooking($booking);

        $this->assertSame('promo', $split['case']);
        $this->assertSame(900_000, $split['driver_amount']);
        $this->assertSame(80_000, $split['admin_amount']);
        $this->assertSame(0, $split['referrer_amount']);
        $this->assertSame(8, $split['admin_percent']);
        $this->assertSame(20_000, $split['absorbed_amount']);
    }

    public function test_referrer_split_is_90_driver_2_admin_8_gt(): void
    {
        $referrer = Mockery::mock(ReferralCode::class)->makePartial();
        $referrer->type = ReferralCode::TYPE_REFERRER;
        $referrer->shouldReceive('commissionPercent')->andReturn(8.0);

        $booking = $this->bookingStub(1_000_000, discountPercent: 0, discountAmount: 0, referrer: $referrer);
        $split = WalletTripFeeSplit::forBooking($booking);

        $this->assertSame('referrer', $split['case']);
        $this->assertSame(900_000, $split['driver_amount']);
        $this->assertSame(20_000, $split['admin_amount']);
        $this->assertSame(80_000, $split['referrer_amount']);
        $this->assertSame(2, $split['admin_percent']);
        $this->assertSame(8, $split['referrer_percent']);
    }

    public function test_referrer_takes_priority_over_promo_discount(): void
    {
        $referrer = Mockery::mock(ReferralCode::class)->makePartial();
        $referrer->type = ReferralCode::TYPE_REFERRER;
        $referrer->shouldReceive('commissionPercent')->andReturn(8.0);

        $booking = $this->bookingStub(500_000, discountPercent: 2, discountAmount: 10_000, referrer: $referrer);
        $split = WalletTripFeeSplit::forBooking($booking);

        $this->assertSame('referrer', $split['case']);
        $this->assertSame(2, $split['admin_percent']);
        $this->assertSame(8, $split['referrer_percent']);
    }

    private function bookingStub(
        int $totalPrice,
        float $discountPercent,
        int $discountAmount,
        ?ReferralCode $referrer,
    ): Booking {
        $booking = Mockery::mock(Booking::class)->makePartial();
        $booking->total_price = $totalPrice;
        $booking->referral_discount_percent = $discountPercent;
        $booking->referral_discount_amount = $discountAmount;
        $booking->shouldReceive('loadMissing')->andReturnSelf();
        $booking->shouldReceive('tripRevenueAmount')->andReturn($totalPrice);
        $booking->setRelation('appliedReferralCode', $referrer);

        return $booking;
    }
}
