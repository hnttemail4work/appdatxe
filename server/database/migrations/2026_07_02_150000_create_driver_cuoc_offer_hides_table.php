<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_cuoc_offer_hides')) {
            return;
        }

        Schema::create('driver_cuoc_offer_hides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('driver_user_id');
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->string('contact_phone', 30);
            $table->timestamps();

            $table->unique(['driver_user_id', 'schedule_id', 'contact_phone'], 'driver_cuoc_offer_hides_unique');
            $table->index('driver_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_cuoc_offer_hides');
    }
};
