<?php

use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (PlatformSetting::query()->where('setting_key', 'referral_commission_percentage')->doesntExist()) {
            PlatformSetting::setValue('referral_commission_percentage', ['value' => 5], 'finance');
        }
    }

    public function down(): void
    {
        PlatformSetting::query()
            ->where('setting_key', 'referral_commission_percentage')
            ->delete();
    }
};
