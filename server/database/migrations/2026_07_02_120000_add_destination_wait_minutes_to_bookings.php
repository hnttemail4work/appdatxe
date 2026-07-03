<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::hasColumn('bookings', 'destination_wait_minutes')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('destination_wait_minutes')
                ->default(0)
                ->after('trip_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'destination_wait_minutes')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('destination_wait_minutes');
        });
    }
};
