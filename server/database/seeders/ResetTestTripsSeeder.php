<?php

namespace Database\Seeders;

use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Chỉ giữ 2 chuyến mẫu để test — admin tự tạo thêm sau. */
class ResetTestTripsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('driver_trip_settlements')->delete();
            DB::table('driver_wallet_transactions')->delete();
            DB::table('booking_audits')->delete();
            DB::table('payment_transactions')->delete();
            DB::table('seat_reservations')->delete();
            DB::table('driver_trip_requests')->delete();
            DB::table('bookings')->delete();
            DB::table('schedules')->delete();
            DB::table('schedule_templates')->delete();

            $ownerId = DB::table('users')->where('role', 'admin')->value('id');
            $vehicle = Vehicle::query()->where('operator_id', $ownerId)->first()
                ?? Vehicle::query()->first();

            if (! $vehicle) {
                return;
            }

            $routes = [
                TripRoute::query()->firstOrCreate(
                    ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu'],
                    ['base_price' => 200000, 'is_active' => true],
                ),
                TripRoute::query()->firstOrCreate(
                    ['departure' => 'TP.HCM', 'destination' => 'Đà Lạt'],
                    ['base_price' => 350000, 'is_active' => true],
                ),
            ];

            ScheduleTemplate::query()->create([
                'route_id'       => $routes[0]->id,
                'vehicle_id'     => $vehicle->id,
                'departure_time' => '06:00:00',
                'driver_id'      => null,
                'driver_name'    => 'Chờ khách đặt',
                'status'         => 'active',
            ]);

            ScheduleTemplate::query()->create([
                'route_id'       => $routes[1]->id,
                'vehicle_id'     => $vehicle->id,
                'departure_time' => '20:00:00',
                'driver_id'      => null,
                'driver_name'    => 'Chờ khách đặt',
                'status'         => 'active',
            ]);
        });
    }
}
