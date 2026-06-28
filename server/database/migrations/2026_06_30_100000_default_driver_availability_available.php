<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('driver_profiles', 'availability_status')) {
            return;
        }

        DB::table('driver_profiles')
            ->where('availability_status', 'off_duty')
            ->where('status', 'active')
            ->update(['availability_status' => 'available']);
    }

    public function down(): void
    {
        // no-op
    }
};
