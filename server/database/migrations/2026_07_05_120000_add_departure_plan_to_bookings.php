<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::hasColumn('bookings', 'departure_plan')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('departure_plan', 20)->default('today')->after('pickup_time');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'departure_plan')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('departure_plan');
        });
    }
};
