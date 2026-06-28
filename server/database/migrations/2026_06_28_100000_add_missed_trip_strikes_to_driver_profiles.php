<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'missed_trip_strikes')) {
                $table->unsignedTinyInteger('missed_trip_strikes')->default(0)->after('availability_status');
            }
            if (! Schema::hasColumn('driver_profiles', 'missed_trip_locked_at')) {
                $table->timestamp('missed_trip_locked_at')->nullable()->after('missed_trip_strikes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_profiles', 'missed_trip_locked_at')) {
                $table->dropColumn('missed_trip_locked_at');
            }
            if (Schema::hasColumn('driver_profiles', 'missed_trip_strikes')) {
                $table->dropColumn('missed_trip_strikes');
            }
        });
    }
};
