<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_daily_penalties')) {
            return;
        }

        Schema::create('driver_daily_penalties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->date('penalty_date');
            $table->unsignedTinyInteger('consecutive_cancel_count')->default(0);
            $table->unsignedTinyInteger('late_continue_timeout_count')->default(0);
            $table->timestamps();

            $table->unique(['driver_profile_id', 'penalty_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_daily_penalties');
    }
};
