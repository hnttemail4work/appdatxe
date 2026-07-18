<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gỡ các key cấu hình tính tiền khỏi platform_settings.
 * Runtime dùng hằng số trong PlatformFees / VehicleTypePricing cho đến khi làm lại.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $keys = [
        'pricing_km_rate_under_100',
        'pricing_km_rate_over_100',
        'app_commission_percentage',
        'commission_percentage',
        'referral_commission_percentage',
        'referral_commission_first_percentage',
        'referral_commission_repeat_percentage',
        'driver_invite_qr_discount_percentage',
        'vehicle_type_whole_car_pricing',
        'vehicle_capacity_whole_car_pricing',
    ];

    public function up(): void
    {
        DB::table('platform_settings')
            ->whereIn('setting_key', $this->keys)
            ->delete();
    }

    public function down(): void
    {
        // Không khôi phục — cấu hình tính tiền đã gỡ, sẽ làm lại sau.
    }
};
