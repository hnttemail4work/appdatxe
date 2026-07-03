<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingPhoneGuardServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fourth_cancel_same_location_flags_repeat_cancel(): void
    {
        $guard = app(BookingPhoneGuardService::class);
        $phone = '0909' . random_int(100000, 999999);
        $norm = preg_replace('/\D+/', '', $phone);
        $locationKey = $guard->locationFingerprint(10.7769, 106.7009, 'HCM', 'Q1');

        Cache::forget('phone_cancel_count:' . $norm . ':' . $locationKey);
        Cache::forget('phone_book_block:' . $norm . ':' . $locationKey);

        $operator = User::factory()->create(['role' => 'operator']);
        $vehicle = Vehicle::factory()->create(['operator_id' => $operator->id]);
        $route = TripRoute::factory()->create();
        $schedule = Schedule::factory()->create([
            'vehicle_id'     => $vehicle->id,
            'route_id'       => $route->id,
            'status'         => 'scheduled',
            'departure_time' => now()->addHour(),
        ]);

        $reasonId = CancellationReason::query()->where('audience', 'customer')->value('id')
            ?? CancellationReason::query()->create([
                'audience' => 'customer',
                'label'    => 'Đổi ý',
                'sort_order' => 1,
            ])->id;

        $workflow = app(BookingWorkflowService::class);

        for ($i = 0; $i < 4; $i++) {
            $booking = Booking::query()->create([
                'contact_phone'     => $phone,
                'passenger_name'    => 'Khách test',
                'passenger_gender'  => 'male',
                'schedule_id'       => $schedule->id,
                'booking_reference' => 'BK-TEST-' . uniqid(),
                'total_price'       => 100000,
                'payment_status'    => 'unpaid',
                'trip_status'       => 'pending',
                'booking_status'    => 'confirmed',
                'pickup_lat'        => 10.7769,
                'pickup_lng'        => 106.7009,
                'pickup_address'    => 'HCM',
                'pickup_detail'     => 'Q1',
            ]);

            $workflow->cancelByPhone(
                $booking,
                $phone,
                $i >= 3 ? (int) $reasonId : null,
            );
        }

        $fresh = $booking->fresh();
        $this->assertTrue($fresh->repeat_cancel_flag);
        $this->assertSame('cancelled', $fresh->operatorListBucket());
    }
}
