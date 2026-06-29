<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'referral_code')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('referral_code');
            });
        }

        DB::table('platform_settings')
            ->where('setting_key', 'referral_commission_percentage')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'referral_code')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('referral_code', 20)->nullable()->after('notes')->index();
            });
        }
    }
};
