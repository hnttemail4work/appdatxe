<?php

use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (PlatformSetting::query()->where('setting_key', 'referral_commission_first_percentage')->doesntExist()) {
            PlatformSetting::setValue('referral_commission_first_percentage', ['value' => 8], 'finance');
        }

        if (PlatformSetting::query()->where('setting_key', 'referral_commission_repeat_percentage')->doesntExist()) {
            PlatformSetting::setValue('referral_commission_repeat_percentage', ['value' => 2], 'finance');
        }

        PlatformSetting::query()
            ->where('setting_key', 'referral_commission_percentage')
            ->delete();
    }

    public function down(): void
    {
        PlatformSetting::query()
            ->whereIn('setting_key', [
                'referral_commission_first_percentage',
                'referral_commission_repeat_percentage',
            ])
            ->delete();
    }
};
