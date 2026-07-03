<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'last_province')) {
                $table->string('last_province', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_profiles', 'last_province')) {
                $table->dropColumn('last_province');
            }
        });
    }
};
