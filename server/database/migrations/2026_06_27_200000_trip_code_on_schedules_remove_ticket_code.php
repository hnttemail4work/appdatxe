<?php

use App\Support\TripCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->string('trip_code', 20)->nullable()->unique()->after('status');
        });

        DB::table('schedules')->orderBy('id')->chunkById(100, function ($schedules): void {
            foreach ($schedules as $schedule) {
                DB::table('schedules')
                    ->where('id', $schedule->id)
                    ->update(['trip_code' => TripCode::generate()]);
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn('ticket_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('ticket_code')->nullable()->unique()->after('booking_mode');
        });

        DB::table('bookings')->orderBy('id')->chunkById(100, function ($bookings): void {
            foreach ($bookings as $booking) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['ticket_code' => 'TCK-' . strtoupper(substr(md5((string) $booking->id), 0, 10))]);
            }
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropUnique(['trip_code']);
            $table->dropColumn('trip_code');
        });
    }
};
