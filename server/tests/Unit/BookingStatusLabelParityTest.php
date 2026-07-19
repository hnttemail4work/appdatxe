<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Presenters\BookingPresenter;
use Tests\TestCase;

class BookingStatusLabelParityTest extends TestCase
{
    public function test_completed_uses_hoan_thanh_on_customer_and_admin(): void
    {
        $booking = new Booking([
            'booking_status' => 'confirmed',
            'trip_status'    => 'completed',
        ]);

        $presenter = new BookingPresenter($booking);

        $this->assertSame('Hoàn thành', $presenter->primaryStatusLabel());
        $this->assertSame('Hoàn thành', $presenter->operatorMonitorLabel());
        $this->assertSame('Hoàn thành', $presenter->tripDisplayLabel());
    }
}
