<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->string('driver_code', 20)->nullable()->unique()->after('user_id');
        });

        Schema::create('driver_trip_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'cancelled'])->default('pending')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        foreach (\Illuminate\Support\Facades\DB::table('driver_profiles')->whereNull('driver_code')->pluck('id') as $profileId) {
            \Illuminate\Support\Facades\DB::table('driver_profiles')
                ->where('id', $profileId)
                ->update(['driver_code' => 'TX' . str_pad((string) $profileId, 6, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trip_requests');

        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn('driver_code');
        });
    }
};
