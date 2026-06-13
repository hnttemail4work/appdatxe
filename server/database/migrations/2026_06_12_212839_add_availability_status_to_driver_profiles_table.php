<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->enum('availability_status', ['available', 'on_trip', 'off_duty'])
                  ->default('off_duty')
                  ->after('status')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn('availability_status');
        });
    }
};
