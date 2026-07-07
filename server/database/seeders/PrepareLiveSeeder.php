<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Support\AdminBootstrapAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Xóa toàn bộ dữ liệu vận hành/test — chỉ giữ admin + cấu hình hệ thống tối thiểu. */
class PrepareLiveSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->wipeOperationalData();
            $this->removeNonAdminUsers();
            AdminBootstrapAccount::ensure();
            $this->seedSystemDefaults();
        });
    }

    private function wipeOperationalData(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tablesToClear() as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /** @return list<string> */
    private function tablesToClear(): array
    {
        return [
            'push_notification_dedup',
            'push_subscriptions',
            'trip_reviews',
            'trip_ledger',
            'driver_cuoc_offer_hides',
            'driver_daily_penalties',
            'driver_trip_settlements',
            'driver_wallet_transactions',
            'driver_wallets',
            'booking_audits',
            'payment_transactions',
            'seat_reservations',
            'driver_trip_requests',
            'schedule_merge_requests',
            'bookings',
            'schedules',
            'schedule_templates',
            'referral_codes',
            'payouts',
            'merchant_profiles',
            'driver_profiles',
            'vehicles',
            'routes',
            'personal_access_tokens',
            'sessions',
            'platform_settings',
            'cancellation_reasons',
        ];
    }

    private function removeNonAdminUsers(): void
    {
        DB::table('users')
            ->where('role', '!=', 'admin')
            ->delete();

        DB::table('users')
            ->where('role', 'admin')
            ->where('email', '!=', AdminBootstrapAccount::LOGIN)
            ->delete();
    }

    private function seedSystemDefaults(): void
    {
        DB::table('cancellation_reasons')->insert([
            ['label' => 'Đổi lịch / đổi chuyến', 'audience' => 'both', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Không còn nhu cầu đi', 'audience' => 'customer', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Khách không đến điểm đón', 'audience' => 'driver', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Xe hỏng / sự cố', 'audience' => 'driver', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Lý do khác', 'audience' => 'both', 'sort_order' => 99, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        PlatformSetting::setValue('commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('app_commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('referral_commission_first_percentage', ['value' => 8], 'finance');
        PlatformSetting::setValue('referral_commission_repeat_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('round_trip_discount_percentage', ['value' => 15], 'finance');
    }
}
