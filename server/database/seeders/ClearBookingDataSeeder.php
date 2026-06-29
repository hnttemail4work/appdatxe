<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Xóa toàn bộ đơn/chuyến đã phát sinh — giữ template tuyến của quản lý. */
class ClearBookingDataSeeder extends Seeder
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

            DB::table('driver_wallets')->update([
                'balance'                     => 0,
                'cumulative_revenue'          => 0,
                'completed_settlements_count' => 0,
                'wallet_gate_enabled'         => false,
                'accept_trips_blocked_at'     => null,
                'accept_trips_block_reason'   => null,
            ]);
        });
    }
}
