<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('booking_mode', 20)->default('shared')->after('trip_type');
            $table->timestamp('operator_confirmed_at')->nullable()->after('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['booking_mode', 'operator_confirmed_at']);
        });
    }
};
