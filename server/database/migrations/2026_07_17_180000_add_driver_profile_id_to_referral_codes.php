<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_codes', 'driver_profile_id')) {
                $table->foreignId('driver_profile_id')
                    ->nullable()
                    ->after('booking_id')
                    ->constrained('driver_profiles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (Schema::hasColumn('referral_codes', 'driver_profile_id')) {
                $table->dropConstrainedForeignId('driver_profile_id');
            }
        });
    }
};
