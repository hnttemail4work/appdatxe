<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_templates') || ! Schema::hasColumn('schedule_templates', 'route_id')) {
            return;
        }

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->dropForeign(['route_id']);
        });

        DB::statement('ALTER TABLE schedule_templates MODIFY route_id BIGINT UNSIGNED NULL');

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->foreign('route_id')->references('id')->on('routes')->nullOnDelete();
        });

        if (Schema::hasColumn('schedule_templates', 'whole_car_price')) {
            DB::statement('ALTER TABLE schedule_templates MODIFY whole_car_price DECIMAL(12,2) NULL');
        }
    }

    public function down(): void
    {
        //
    }
};
