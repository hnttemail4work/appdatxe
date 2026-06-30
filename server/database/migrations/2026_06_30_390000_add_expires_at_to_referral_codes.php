<?php

use App\Models\ReferralCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_codes', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('activated_at');
            }
        });

        ReferralCode::query()
            ->where('type', ReferralCode::TYPE_BOOKING_TEMP)
            ->where('status', ReferralCode::STATUS_ACTIVE)
            ->whereNotNull('activated_at')
            ->whereNull('expires_at')
            ->each(function (ReferralCode $code): void {
                $code->updateQuietly([
                    'expires_at' => $code->activated_at->copy()->addDays(ReferralCode::BOOKING_CODE_VALIDITY_DAYS),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            if (Schema::hasColumn('referral_codes', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
