<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable()->after('id_number');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE driver_profiles DROP FOREIGN KEY driver_profiles_operator_id_foreign');
            DB::statement('ALTER TABLE driver_profiles MODIFY operator_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE driver_profiles ADD CONSTRAINT driver_profiles_operator_id_foreign FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('driver_profiles')->whereNull('operator_id')->delete();
            DB::statement('ALTER TABLE driver_profiles DROP FOREIGN KEY driver_profiles_operator_id_foreign');
            DB::statement('ALTER TABLE driver_profiles MODIFY operator_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE driver_profiles ADD CONSTRAINT driver_profiles_operator_id_foreign FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('date_of_birth');
        });
    }
};
