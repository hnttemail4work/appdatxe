<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripReview;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GuestTripWatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTripReviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedBookingChain(array $bookingOverrides = [], array $scheduleOverrides = []): array
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driverUser = User::factory()->create(['role' => 'driver', 'name' => 'Tài Xế Test']);

        $driverProfile = DriverProfile::query()->create([
            'user_id'             => $driverUser->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX000099',
            'license_number'      => 'L123',
            'license_class'       => 'B2',
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'preference_likes'    => 0,
            'preference_dislikes' => 0,
        ]);

        $route = TripRoute::query()->create([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
            'base_price'  => 200000,
            'distance_km' => 95,
            'is_active'   => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51A-99999',
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $schedule = Schedule::query()->create(array_merge([
            'route_id'        => $route->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => $driverUser->id,
            'driver_name'     => $driverUser->name,
            'departure_time'  => now()->addDay(),
            'service_date'    => now()->addDay()->toDateString(),
            'available_seats' => 4,
            'status'          => 'scheduled',
            'trip_code'       => 'GTTEST1',
        ], $scheduleOverrides));

        $booking = Booking::query()->create(array_merge([
            'contact_phone'     => '0909123456',
            'passenger_name'    => 'Khách Test',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'seat_numbers'      => ['1'],
            'trip_type'         => 'one_way',
            'booking_mode'      => 'shared',
            'booking_reference' => 'BK-TEST-' . uniqid(),
            'total_price'       => 200000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'dropoff_address'   => 'Vũng Tàu',
        ], $bookingOverrides));

        return compact('operator', 'driverUser', 'driverProfile', 'route', 'vehicle', 'schedule', 'booking');
    }

    public function test_guest_trip_watch_lists_session_booking(): void
    {
        $data = $this->seedBookingChain();
        $booking = $data['booking'];

        $this->withSession([
            GuestTripWatchService::SESSION_KEY => [[
                'ref'      => $booking->booking_reference,
                'phone'    => $booking->contact_phone,
                'added_at' => now()->toIso8601String(),
            ]],
        ])->get('/guest/trip-watch')
            ->assertOk()
            ->assertJsonPath('trips.0.booking_ref', $booking->booking_reference)
            ->assertJsonPath('trips.0.progress', 'booked')
            ->assertJsonPath('trips.0.can_review', false);
    }

    public function test_guest_can_submit_review_after_completion(): void
    {
        $data = $this->seedBookingChain([
            'trip_status'  => 'completed',
            'completed_at' => now(),
        ], [
            'status' => 'completed',
        ]);
        $booking = $data['booking'];
        $driverProfile = $data['driverProfile'];

        $this->withSession([
            GuestTripWatchService::SESSION_KEY => [[
                'ref'      => $booking->booking_reference,
                'phone'    => $booking->contact_phone,
                'added_at' => now()->toIso8601String(),
            ]],
        ]);

        $this->get('/guest/trip-watch')
            ->assertJsonPath('trips.0.can_review', true);

        $this->postJson('/guest/trip-reviews', [
            'booking_ref'   => $booking->booking_reference,
            'contact_phone' => $booking->contact_phone,
            'sentiment'     => 'like',
            'comment'       => 'Tài xế rất tốt',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('trip_reviews', [
            'booking_id' => $booking->id,
            'sentiment'  => 'like',
        ]);

        $driverProfile->refresh();
        $this->assertSame(1, $driverProfile->preference_likes);
        $this->assertSame(0, $driverProfile->preference_dislikes);

        $this->get('/guest/trip-watch')
            ->assertJsonPath('trips', []);
    }

    public function test_duplicate_review_is_rejected(): void
    {
        $data = $this->seedBookingChain([
            'trip_status'  => 'completed',
            'completed_at' => now(),
        ]);
        $booking = $data['booking'];

        TripReview::query()->create([
            'booking_id'        => $booking->id,
            'schedule_id'       => $booking->schedule_id,
            'driver_id'         => $data['schedule']->driver_id,
            'driver_profile_id' => $data['driverProfile']->id,
            'sentiment'         => TripReview::SENTIMENT_LIKE,
            'comment'           => null,
            'contact_phone'     => $booking->contact_phone,
        ]);

        $this->withSession([
            GuestTripWatchService::SESSION_KEY => [[
                'ref'      => $booking->booking_reference,
                'phone'    => $booking->contact_phone,
                'added_at' => now()->toIso8601String(),
            ]],
        ])->postJson('/guest/trip-reviews', [
            'booking_ref'   => $booking->booking_reference,
            'contact_phone' => $booking->contact_phone,
            'sentiment'     => 'dislike',
        ])->assertStatus(422);
    }

    public function test_expired_review_window_is_hidden(): void
    {
        $data = $this->seedBookingChain([
            'trip_status'  => 'completed',
            'completed_at' => now()->subDays(3),
        ]);
        $booking = $data['booking'];

        $this->withSession([
            GuestTripWatchService::SESSION_KEY => [[
                'ref'      => $booking->booking_reference,
                'phone'    => $booking->contact_phone,
                'added_at' => now()->subDays(3)->toIso8601String(),
            ]],
        ])->get('/guest/trip-watch')
            ->assertJsonPath('trips', []);
    }

    public function test_home_page_includes_guest_trip_watch_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('guest-trip-watch-root', false)
            ->assertSee('guest-trip-watch.js', false);
    }

    public function test_admin_and_operator_dashboards_still_load(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $data = $this->seedBookingChain();
        $operator = $data['operator'];
        $this->actingAs($operator)->get('/operator/dashboard')->assertOk();
    }
}
