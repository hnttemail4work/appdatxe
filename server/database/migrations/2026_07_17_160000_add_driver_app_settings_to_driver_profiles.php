<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_profiles', 'locale')) {
                $table->string('locale', 8)->default('vi')->after('availability_status');
            }
            if (! Schema::hasColumn('driver_profiles', 'sound_trip_enabled')) {
                $table->boolean('sound_trip_enabled')->default(true)->after('locale');
            }
            if (! Schema::hasColumn('driver_profiles', 'sound_alert_enabled')) {
                $table->boolean('sound_alert_enabled')->default(true)->after('sound_trip_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            foreach (['locale', 'sound_trip_enabled', 'sound_alert_enabled'] as $col) {
                if (Schema::hasColumn('driver_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
