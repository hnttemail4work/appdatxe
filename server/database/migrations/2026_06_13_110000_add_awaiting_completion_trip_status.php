<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN trip_status ENUM('pending', 'confirmed', 'awaiting_completion', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('bookings')->where('trip_status', 'awaiting_completion')->update(['trip_status' => 'confirmed']);
            DB::statement("ALTER TABLE bookings MODIFY COLUMN trip_status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
