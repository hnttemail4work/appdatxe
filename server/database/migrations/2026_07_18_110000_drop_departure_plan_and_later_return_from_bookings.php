<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (Schema::hasColumn('bookings', 'later_return_booking_id')) {
                    $table->dropConstrainedForeignId('later_return_booking_id');
                }
            });

            Schema::table('bookings', function (Blueprint $table) {
                $drop = [];
                foreach (['departure_plan', 'later_return_days', 'later_pickup_dispatched_at'] as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $drop[] = $column;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('platform_settings')) {
            DB::table('platform_settings')->whereIn('setting_key', [
                'departure_plan_surcharge_today_percentage',
                'departure_plan_surcharge_tomorrow_percentage',
                'departure_plan_surcharge_later_per_day_percentage',
                'round_trip_discount_percentage',
            ])->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'departure_plan')) {
                $table->string('departure_plan', 20)->default('oneway')->after('pickup_time');
            }
            if (! Schema::hasColumn('bookings', 'later_return_days')) {
                $table->unsignedSmallInteger('later_return_days')->nullable()->after('departure_plan');
            }
            if (! Schema::hasColumn('bookings', 'later_return_booking_id')) {
                $table->foreignId('later_return_booking_id')
                    ->nullable()
                    ->constrained('bookings')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'later_pickup_dispatched_at')) {
                $table->timestamp('later_pickup_dispatched_at')->nullable();
            }
        });
    }
};
