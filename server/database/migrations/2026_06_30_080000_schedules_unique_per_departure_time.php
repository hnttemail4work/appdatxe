<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'template_id')) {
                $table->index('template_id', 'schedules_template_id_index');
            }
        });

        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'template_id')
                && Schema::hasColumn('schedules', 'service_date')) {
                try {
                    $table->dropUnique(['template_id', 'service_date']);
                } catch (\Throwable) {
                    // Index có thể đã được thay trước đó.
                }
            }
        });

        Schema::table('schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('schedules', 'template_id')
                && Schema::hasColumn('schedules', 'service_date')
                && Schema::hasColumn('schedules', 'departure_time')) {
                try {
                    $table->unique(
                        ['template_id', 'service_date', 'departure_time'],
                        'schedules_template_date_departure_unique',
                    );
                } catch (\Throwable) {
                    // Đã tồn tại sau lần chạy trước.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            try {
                $table->dropUnique('schedules_template_date_departure_unique');
            } catch (\Throwable) {
            }

            if (Schema::hasColumn('schedules', 'template_id')
                && Schema::hasColumn('schedules', 'service_date')) {
                try {
                    $table->unique(['template_id', 'service_date']);
                } catch (\Throwable) {
                }
            }

            try {
                $table->dropIndex('schedules_template_id_index');
            } catch (\Throwable) {
            }
        });
    }
};
