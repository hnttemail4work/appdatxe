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
            $table->string('photo_id_card_back')->nullable()->after('photo_id_card');
            $table->json('photo_vehicles')->nullable()->after('photo_vehicle');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn(['photo_id_card_back', 'photo_vehicles']);
        });
    }
};
