<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_profile_id')->nullable()->constrained('driver_profiles')->nullOnDelete();
            $table->enum('sentiment', ['like', 'dislike']);
            $table->text('comment')->nullable();
            $table->string('contact_phone', 30);
            $table->timestamps();

            $table->index(['driver_profile_id', 'created_at']);
            $table->index(['schedule_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reviews');
    }
};
