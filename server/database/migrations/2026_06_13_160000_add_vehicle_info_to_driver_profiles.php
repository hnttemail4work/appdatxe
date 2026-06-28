<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->string('vehicle_license_plate')->nullable()->after('bank_account');
            $table->enum('vehicle_type', ['limousine', 'sedan', 'suv'])->nullable()->after('vehicle_license_plate');
            $table->string('vehicle_brand')->nullable()->after('vehicle_type');
            $table->string('vehicle_model')->nullable()->after('vehicle_brand');
            $table->string('vehicle_color')->nullable()->after('vehicle_model');
            $table->unsignedTinyInteger('vehicle_seats')->nullable()->after('vehicle_color');
            $table->string('photo_license_front')->nullable()->after('photo_id_card_back');
            $table->string('photo_license_back')->nullable()->after('photo_license_front');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'vehicle_license_plate',
                'vehicle_type',
                'vehicle_brand',
                'vehicle_model',
                'vehicle_color',
                'vehicle_seats',
                'photo_license_front',
                'photo_license_back',
            ]);
        });
    }
};
