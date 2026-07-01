<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'driver_stage')) {
                $table->string('driver_stage', 32)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'driver_stage')) {
                $table->dropColumn('driver_stage');
            }
        });
    }
};
