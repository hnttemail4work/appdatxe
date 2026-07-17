<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicles') || ! Schema::hasColumn('vehicles', 'type')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE vehicles MODIFY type VARCHAR(40) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vehicles') || ! Schema::hasColumn('vehicles', 'type')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Map kiểu mới về family trước khi thu hẹp ENUM.
            DB::table('vehicles')->orderBy('id')->each(function ($row): void {
                $family = \App\Support\DriverVehicleOptions::family((string) $row->type);
                $legacy = in_array($family, ['limousine', 'sedan', 'suv'], true) ? $family : 'sedan';
                DB::table('vehicles')->where('id', $row->id)->update(['type' => $legacy]);
            });

            DB::statement("ALTER TABLE vehicles MODIFY type ENUM('limousine', 'sedan', 'suv') NOT NULL");
        }
    }
};
