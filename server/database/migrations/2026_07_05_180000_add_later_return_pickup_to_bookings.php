<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'later_return_booking_id')) {
                $table->foreignId('later_return_booking_id')
                    ->nullable()
                    ->after('departure_plan')
                    ->constrained('bookings')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bookings', 'later_pickup_dispatched_at')) {
                $table->timestamp('later_pickup_dispatched_at')->nullable()->after('later_return_booking_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'later_return_booking_id')) {
                $table->dropConstrainedForeignId('later_return_booking_id');
            }

            if (Schema::hasColumn('bookings', 'later_pickup_dispatched_at')) {
                $table->dropColumn('later_pickup_dispatched_at');
            }
        });
    }
};
