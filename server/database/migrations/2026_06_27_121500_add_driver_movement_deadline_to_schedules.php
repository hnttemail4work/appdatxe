<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'driver_assigned_at')) {
                $table->timestamp('driver_assigned_at')->nullable()->after('driver_stage');
            }
            if (! Schema::hasColumn('schedules', 'driver_movement_deadline_at')) {
                $table->timestamp('driver_movement_deadline_at')->nullable()->after('driver_assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'driver_movement_deadline_at')) {
                $table->dropColumn('driver_movement_deadline_at');
            }
            if (Schema::hasColumn('schedules', 'driver_assigned_at')) {
                $table->dropColumn('driver_assigned_at');
            }
        });
    }
};
