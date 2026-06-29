<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'last_lat')) {
                $table->decimal('last_lat', 10, 7)->nullable()->after('preference_dislikes');
            }
            if (! Schema::hasColumn('driver_profiles', 'last_lng')) {
                $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            }
            if (! Schema::hasColumn('driver_profiles', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable()->after('last_lng');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'pickup_lat')) {
                $after = Schema::hasColumn('bookings', 'pickup_detail') ? 'pickup_detail' : 'pickup_address';
                $table->decimal('pickup_lat', 10, 7)->nullable()->after($after);
            }
            if (! Schema::hasColumn('bookings', 'pickup_lng')) {
                $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['last_lat', 'last_lng', 'last_location_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng']);
        });
    }
};
