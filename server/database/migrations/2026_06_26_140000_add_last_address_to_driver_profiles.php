<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_profiles', 'last_address')) {
                $table->string('last_address', 500)->nullable()->after('last_province');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('driver_profiles', 'last_address')) {
                $table->dropColumn('last_address');
            }
        });
    }
};
