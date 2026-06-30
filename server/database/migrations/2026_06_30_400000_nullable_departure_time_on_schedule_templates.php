<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_templates') || ! Schema::hasColumn('schedule_templates', 'departure_time')) {
            return;
        }

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->time('departure_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_templates') || ! Schema::hasColumn('schedule_templates', 'departure_time')) {
            return;
        }

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->time('departure_time')->nullable(false)->change();
        });
    }
};
