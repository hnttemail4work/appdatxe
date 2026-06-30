<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'driver_search_started_at')) {
                $table->timestamp('driver_search_started_at')->nullable()->after('hold_expires_at');
            }
            if (! Schema::hasColumn('bookings', 'needs_operator_help_at')) {
                $table->timestamp('needs_operator_help_at')->nullable()->after('driver_search_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'needs_operator_help_at')) {
                $table->dropColumn('needs_operator_help_at');
            }
            if (Schema::hasColumn('bookings', 'driver_search_started_at')) {
                $table->dropColumn('driver_search_started_at');
            }
        });
    }
};
