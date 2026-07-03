<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('schedule_merge_requests');
        Schema::dropIfExists('seat_reservations');

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                foreach ([
                    'seat_numbers',
                    'trip_type',
                    'booking_mode',
                    'vehicle_count',
                    'vehicle_capacity',
                    'destination_wait_minutes',
                ] as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('schedule_templates')) {
            Schema::table('schedule_templates', function (Blueprint $table): void {
                foreach (['seat_price', 'whole_car_round_trip_price', 'seat_round_trip_price'] as $column) {
                    if (Schema::hasColumn('schedule_templates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table): void {
                foreach (['seat_price', 'available_seats'] as $column) {
                    if (Schema::hasColumn('schedules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('routes')) {
            Schema::table('routes', function (Blueprint $table): void {
                if (Schema::hasColumn('routes', 'round_trip_discount_percent')) {
                    $table->dropColumn('round_trip_discount_percent');
                }
            });
        }
    }

    public function down(): void
    {
        // Không khôi phục schema cũ — migration một chiều.
    }
};
