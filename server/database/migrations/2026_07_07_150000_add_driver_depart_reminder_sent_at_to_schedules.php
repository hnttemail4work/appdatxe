<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'driver_depart_reminder_sent_at')) {
                $table->timestamp('driver_depart_reminder_sent_at')
                    ->nullable()
                    ->after('driver_late_pickup_continue_deadline_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'driver_depart_reminder_sent_at')) {
                $table->dropColumn('driver_depart_reminder_sent_at');
            }
        });
    }
};
