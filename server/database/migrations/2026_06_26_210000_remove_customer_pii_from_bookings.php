<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'customer')->delete();

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                if (Schema::hasIndex('bookings', 'bookings_customer_id_booking_status_index')) {
                    $table->dropIndex('bookings_customer_id_booking_status_index');
                }
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('bookings', 'guest_name')) {
                $table->dropColumn('guest_name');
            }
        });

        if (Schema::hasColumn('bookings', 'guest_phone') && ! Schema::hasColumn('bookings', 'contact_phone')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->renameColumn('guest_phone', 'contact_phone');
            });
        } elseif (! Schema::hasColumn('bookings', 'contact_phone')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->string('contact_phone', 30)->nullable()->after('schedule_id');
            });
        }

        Schema::table('driver_trip_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_trip_requests', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                if (Schema::hasIndex('driver_trip_requests', 'driver_trip_requests_customer_id_status_index')) {
                    $table->dropIndex('driver_trip_requests_customer_id_status_index');
                }
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('driver_trip_requests', 'guest_name')) {
                $table->dropColumn('guest_name');
            }
        });

        if (Schema::hasColumn('driver_trip_requests', 'guest_phone') && ! Schema::hasColumn('driver_trip_requests', 'contact_phone')) {
            Schema::table('driver_trip_requests', function (Blueprint $table): void {
                $table->renameColumn('guest_phone', 'contact_phone');
            });
        }

        if (Schema::hasColumn('seat_reservations', 'customer_id')) {
            Schema::table('seat_reservations', function (Blueprint $table): void {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }
    }

    public function down(): void
    {
        // Irreversible — customer accounts and PII are intentionally removed.
    }
};
