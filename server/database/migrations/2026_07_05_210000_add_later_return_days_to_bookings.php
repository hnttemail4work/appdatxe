<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::hasColumn('bookings', 'later_return_days')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('later_return_days')->nullable()->after('departure_plan');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'later_return_days')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('later_return_days');
        });
    }
};
