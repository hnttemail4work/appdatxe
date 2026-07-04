<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Services\BookingBrowserGuardService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class BookingBrowserGuardServiceTest extends TestCase
{
    public function test_blocks_after_cancel_limit(): void
    {
        Cache::flush();
        $service = app(BookingBrowserGuardService::class);
        $browserId = 'test-browser-abc';

        $this->assertFalse($service->isCancelBlocked($browserId));

        $service->recordCancel($browserId);
        $service->recordCancel($browserId);
        $this->assertFalse($service->isCancelBlocked($browserId));

        $service->recordCancel($browserId);
        $this->assertTrue($service->isCancelBlocked($browserId));

        $this->expectException(InvalidArgumentException::class);
        $service->assertCanBook($browserId);
    }

    public function test_records_and_clears_active_booking_cache(): void
    {
        Cache::flush();
        $service = app(BookingBrowserGuardService::class);
        $browserId = 'browser-abc';
        $booking = new Booking(['booking_reference' => 'REF-CACHE-1']);

        $service->recordActiveBooking($browserId, $booking);

        $this->assertSame('REF-CACHE-1', Cache::get($service->activeBookingCacheKey($browserId)));
        $this->assertSame($browserId, Cache::get($service->bookingRefCacheKey('REF-CACHE-1')));

        $service->clearActiveBooking($browserId, 'REF-CACHE-1');

        $this->assertNull(Cache::get($service->activeBookingCacheKey($browserId)));
        $this->assertNull(Cache::get($service->bookingRefCacheKey('REF-CACHE-1')));
    }

    public function test_requires_browser_id_to_book(): void
    {
        Cache::flush();
        $service = app(BookingBrowserGuardService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->assertCanBook(null);
    }
}
