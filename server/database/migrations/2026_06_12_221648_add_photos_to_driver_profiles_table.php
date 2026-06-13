<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            // Paths relative to storage/app/public — DB never stores binary
            $table->string('photo_portrait')->nullable()->after('notes');  // chân dung
            $table->string('photo_id_card')->nullable()->after('photo_portrait');   // CCCD
            $table->string('photo_vehicle')->nullable()->after('photo_id_card');    // xe
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            $table->dropColumn(['photo_portrait', 'photo_id_card', 'photo_vehicle']);
        });
    }
};
