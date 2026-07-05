<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('catalog_vehicle_photo_index')->default(0)->after('photo_vehicles');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn('catalog_vehicle_photo_index');
        });
    }
};
