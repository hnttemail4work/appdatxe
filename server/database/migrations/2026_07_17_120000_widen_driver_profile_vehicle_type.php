<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_profiles') || ! Schema::hasColumn('driver_profiles', 'vehicle_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE driver_profiles MODIFY vehicle_type VARCHAR(40) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('driver_profiles') || ! Schema::hasColumn('driver_profiles', 'vehicle_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE driver_profiles MODIFY vehicle_type ENUM('limousine', 'sedan', 'suv') NULL");
        }
    }
};
