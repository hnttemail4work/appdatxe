<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('passenger_gender', ['male', 'female'])->default('male')->after('passenger_name');
            $table->unsignedTinyInteger('passenger_age')->nullable()->after('passenger_gender');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['passenger_gender', 'passenger_age']);
        });
    }
};
