<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->json('photos')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profile_change_requests');
    }
};
