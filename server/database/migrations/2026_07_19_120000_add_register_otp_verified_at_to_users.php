<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'register_otp_verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('register_otp_verified_at')->nullable()->after('approval_status');
        });

        // Tài khoản đã duyệt trước đây: không bắt OTP lần đầu lại.
        if (Schema::hasColumn('users', 'approval_status')) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('role', 'customer')
                ->where('approval_status', 'approved')
                ->whereNull('register_otp_verified_at')
                ->update(['register_otp_verified_at' => now()]);
        }

        if (Schema::hasTable('driver_profiles')) {
            $driverUserIds = \Illuminate\Support\Facades\DB::table('driver_profiles')
                ->where('approval_status', 'approved')
                ->pluck('user_id');
            if ($driverUserIds->isNotEmpty()) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->whereIn('id', $driverUserIds)
                    ->whereNull('register_otp_verified_at')
                    ->update(['register_otp_verified_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'register_otp_verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('register_otp_verified_at');
        });
    }
};
