<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_profiles', 'sound_enabled')) {
                $table->boolean('sound_enabled')->default(true)->after('locale');
            }
            if (! Schema::hasColumn('driver_profiles', 'sound_preset')) {
                $table->string('sound_preset', 20)->default('tone1')->after('sound_enabled');
            }
        });

        if (Schema::hasColumn('driver_profiles', 'sound_trip_enabled')) {
            DB::table('driver_profiles')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $enabled = (bool) ($row->sound_trip_enabled ?? true)
                        || (bool) ($row->sound_alert_enabled ?? true);
                    DB::table('driver_profiles')->where('id', $row->id)->update([
                        'sound_enabled' => $enabled,
                        'sound_preset'  => 'tone1',
                    ]);
                }
            });
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            foreach (['sound_trip_enabled', 'sound_alert_enabled'] as $col) {
                if (Schema::hasColumn('driver_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_profiles', 'sound_trip_enabled')) {
                $table->boolean('sound_trip_enabled')->default(true)->after('locale');
            }
            if (! Schema::hasColumn('driver_profiles', 'sound_alert_enabled')) {
                $table->boolean('sound_alert_enabled')->default(true)->after('sound_trip_enabled');
            }
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            foreach (['sound_enabled', 'sound_preset'] as $col) {
                if (Schema::hasColumn('driver_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
