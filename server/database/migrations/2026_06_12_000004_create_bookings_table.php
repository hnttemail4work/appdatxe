<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->json('seat_numbers');
            $table->string('ticket_code')->unique();
            $table->string('booking_reference')->unique();
            $table->decimal('total_price', 12, 2);
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid')->index();
            $table->enum('trip_status', ['confirmed', 'completed', 'cancelled'])->default('confirmed')->index();
            $table->enum('booking_status', ['pending', 'confirmed', 'rejected', 'cancelled'])->default('pending')->index();
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'booking_status']);
            $table->index(['schedule_id', 'trip_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
