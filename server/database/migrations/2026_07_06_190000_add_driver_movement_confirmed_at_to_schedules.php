<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'driver_movement_confirmed_at')) {
                $table->timestamp('driver_movement_confirmed_at')->nullable()->after('driver_movement_deadline_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'driver_movement_confirmed_at')) {
                $table->dropColumn('driver_movement_confirmed_at');
            }
        });
    }
};
