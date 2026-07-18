<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->foreignId('assigned_driver_profile_id')
                ->nullable()
                ->after('driver_profile_id')
                ->constrained('driver_profiles')
                ->nullOnDelete();
            $table->index('assigned_driver_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_driver_profile_id');
        });
    }
};
