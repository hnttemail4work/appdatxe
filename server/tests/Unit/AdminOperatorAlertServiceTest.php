<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Services\AdminOperatorAlertService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminOperatorAlertServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_record_driver_accepted_queues_alert_for_admin_poll(): void
    {
        $route = new TripRoute([
            'departure'   => 'Hà Nội',
            'destination' => 'Ninh Bình',
        ]);

        $schedule = new Schedule([
            'driver_name' => 'Nguyễn Văn A',
        ]);
        $schedule->setRelation('route', $route);

        $booking = new Booking([
            'id'                => 42,
            'passenger_name'    => 'Khách Test',
            'booking_reference' => 'BK-001',
        ]);
        $booking->setRelation('schedule', $schedule);

        $service = app(AdminOperatorAlertService::class);
        $service->recordDriverAccepted($booking);

        $alerts = $service->pullAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('driver_accepted', $alerts[0]['type']);
        $this->assertSame('Tài xế đã nhận cuốc', $alerts[0]['title']);
        $this->assertStringContainsString('Nguyễn Văn A', $alerts[0]['message']);
        $this->assertStringContainsString('BK-001', $alerts[0]['message']);
        $this->assertStringContainsString('Khách Test', $alerts[0]['message']);
        $this->assertStringContainsString('Hà Nội → Ninh Bình', $alerts[0]['message']);
        $this->assertSame([], $service->pullAlerts());
    }
}
