<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Services\BookingBrowserGuardService;
use App\Services\GuestTripStatusService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuestTripReviewResolveTest extends TestCase
{
    public function test_guest_can_view_with_matching_phone(): void
    {
        $booking = new Booking([
            'booking_reference' => 'REF-REVIEW-2',
            'contact_phone'     => '0901234567',
        ]);

        $service = app(GuestTripStatusService::class);
        $method = new \ReflectionMethod($service, 'guestCanView');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $booking, null, '0901234567'));
        $this->assertFalse($method->invoke($service, $booking, null, '0999999999'));
    }

    public function test_guest_can_view_with_browser_cache(): void
    {
        Cache::flush();

        $browserId = 'browser-review-test';
        $booking = new Booking(['booking_reference' => 'REF-REVIEW-3']);

        app(BookingBrowserGuardService::class)->recordActiveBooking($browserId, $booking);

        $service = app(GuestTripStatusService::class);
        $method = new \ReflectionMethod($service, 'guestCanView');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $booking, $browserId, null));
    }

    public function test_browser_session_kept_for_completed_trip_awaiting_review(): void
    {
        $booking = new Booking([
            'booking_status' => 'confirmed',
            'trip_status'  => 'completed',
        ]);
        $booking->setRelation('tripReview', null);

        $guard = app(BookingBrowserGuardService::class);
        $method = new \ReflectionMethod($guard, 'bookingStillActive');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($guard, $booking));
    }

    public function test_browser_session_not_kept_after_review_submitted(): void
    {
        $booking = new Booking([
            'booking_status' => 'confirmed',
            'trip_status'  => 'completed',
        ]);
        $booking->setRelation('tripReview', new \App\Models\TripReview());

        $guard = app(BookingBrowserGuardService::class);
        $method = new \ReflectionMethod($guard, 'bookingStillActive');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($guard, $booking));
    }
}
