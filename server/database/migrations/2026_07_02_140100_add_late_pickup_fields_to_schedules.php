<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'driver_late_pickup_prompt_at')) {
                $table->timestamp('driver_late_pickup_prompt_at')->nullable()->after('driver_movement_deadline_at');
            }
            if (! Schema::hasColumn('schedules', 'driver_late_pickup_continue_deadline_at')) {
                $table->timestamp('driver_late_pickup_continue_deadline_at')->nullable()->after('driver_late_pickup_prompt_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'driver_late_pickup_continue_deadline_at')) {
                $table->dropColumn('driver_late_pickup_continue_deadline_at');
            }
            if (Schema::hasColumn('schedules', 'driver_late_pickup_prompt_at')) {
                $table->dropColumn('driver_late_pickup_prompt_at');
            }
        });
    }
};
