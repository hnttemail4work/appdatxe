<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_merge_requests')) {
            return;
        }

        Schema::create('schedule_merge_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('source_schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('driver_note', 500)->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['target_schedule_id', 'source_schedule_id', 'status'], 'sched_merge_pair_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_merge_requests');
    }
};
