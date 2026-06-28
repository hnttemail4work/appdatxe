<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'passenger_name')) {
                $table->string('passenger_name', 255)->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('bookings', 'pickup_detail')) {
                $table->string('pickup_detail', 500)->nullable()->after('pickup_address');
            }
            if (! Schema::hasColumn('bookings', 'dropoff_detail')) {
                $table->string('dropoff_detail', 500)->nullable()->after('dropoff_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['passenger_name', 'pickup_detail', 'dropoff_detail']);
        });
    }
};
