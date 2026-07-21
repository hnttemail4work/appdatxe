<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('referral_codes')
            ->where('type', 'booking_temp')
            ->orWhere(function ($query): void {
                $query->where('type', 'referrer')
                    ->whereNotNull('driver_profile_id')
                    ->where('commission_percent', 0);
            })
            ->delete();
    }

    public function down(): void
    {
        // Deleted legacy referral codes cannot be reconstructed.
    }
};
