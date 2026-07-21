<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->string('contact_phone', 30);
            $table->string('phone_key', 16)->comment('9 số cuối chuẩn hoá');
            $table->string('passenger_name')->nullable();
            $table->foreignId('referral_code_id')->nullable()->constrained('referral_codes')->nullOnDelete();
            $table->foreignId('first_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('last_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->unsignedInteger('bookings_count')->default(1);
            $table->timestamp('last_booked_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_profile_id', 'phone_key']);
            $table->index('phone_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_customers');
    }
};
