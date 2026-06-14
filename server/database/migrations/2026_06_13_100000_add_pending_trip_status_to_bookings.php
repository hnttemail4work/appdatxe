<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN trip_status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        DB::table('bookings')
            ->where('booking_status', 'pending')
            ->update(['trip_status' => 'pending']);

        DB::table('bookings')
            ->where('booking_status', 'confirmed')
            ->where('payment_status', 'paid')
            ->update(['trip_status' => 'confirmed']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('bookings')->where('trip_status', 'pending')->update(['trip_status' => 'confirmed']);
            DB::statement("ALTER TABLE bookings MODIFY COLUMN trip_status ENUM('confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'confirmed'");
        }
    }
};
