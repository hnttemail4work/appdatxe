<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'dropoff_lat')) {
                $after = Schema::hasColumn('bookings', 'dropoff_detail') ? 'dropoff_detail' : 'dropoff_address';
                $table->decimal('dropoff_lat', 10, 7)->nullable()->after($after);
                $table->decimal('dropoff_lng', 10, 7)->nullable()->after('dropoff_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'dropoff_lat')) {
                $table->dropColumn(['dropoff_lat', 'dropoff_lng']);
            }
        });
    }
};
