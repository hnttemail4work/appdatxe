<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\PlatformSetting;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverCatalogService;
use App\Services\TripPricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'gozvietadmin')->first()
            ?? User::query()->where('email', 'admin@appdatxe.test')->first()
            ?? User::query()->where('role', 'admin')->first();

        $adminData = [
            'email'    => 'gozvietadmin',
            'name'     => 'Admin',
            'password' => Hash::make('2026g0zv!3tm@n@g3r'),
            'phone'    => null,
            'role'     => 'admin',
            'status'   => 'active',
        ];

        if ($admin) {
            $admin->update($adminData);
        } else {
            $admin = User::query()->create($adminData);
        }

        PlatformSetting::setValue('commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('app_commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('referral_commission_first_percentage', ['value' => 8], 'finance');
        PlatformSetting::setValue('referral_commission_repeat_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('round_trip_discount_percentage', ['value' => 15], 'finance');

        PlatformSetting::setValue('platform_bank', [
            'bank_name'    => 'VietinBank',
            'bank_bin'     => '970415',
            'account'      => '108887132437',
            'account_name' => 'HỒ NGỌC THANH TÂM',
        ], 'finance');

        // Routes — tên tỉnh khớp SouthernProvinces (TP.HCM & lân cận)
        $routeData = [
            ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu',   'base_price' => 200000, 'distance_km' => 95],
            ['departure' => 'TP.HCM', 'destination' => 'Đà Lạt',     'base_price' => 350000, 'distance_km' => 310],
            ['departure' => 'TP.HCM', 'destination' => 'Mũi Né',     'base_price' => 280000, 'distance_km' => 220],
            ['departure' => 'TP.HCM', 'destination' => 'Cần Thơ',    'base_price' => 250000, 'distance_km' => 170],
            ['departure' => 'TP.HCM', 'destination' => 'Mỹ Tho',     'base_price' => 180000, 'distance_km' => 70],
            ['departure' => 'TP.HCM', 'destination' => 'Bình Dương', 'base_price' => 120000, 'distance_km' => 25],
        ];

        foreach ($routeData as $r) {
            TripRoute::query()->updateOrCreate(
                ['departure' => $r['departure'], 'destination' => $r['destination']],
                [
                    'base_price'  => $r['base_price'],
                    'distance_km' => $r['distance_km'],
                    'is_active'   => true,
                ],
            );
        }

        // Tài xế 1 — mỗi tài xế một xe (đồng bộ catalog từ hồ sơ)
        $driverUser = User::query()->firstOrCreate(
            ['email' => 'driver@appdatxe.test'],
            [
                'name'     => 'Nguyễn Văn Tài',
                'password' => Hash::make('password'),
                'phone'    => '0900000003',
                'role'     => 'driver',
                'status'   => 'active',
            ],
        );

        $driverProfile = DriverProfile::query()->firstOrCreate(
            ['user_id' => $driverUser->id],
            [
                'operator_id'         => $admin->id,
                'license_number'      => '12345678901',
                'license_class'       => 'D',
                'license_expiry'      => now()->addYears(3)->toDateString(),
                'experience_years'    => 8,
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => 'available',
                'last_lat'            => 10.7777605,
                'last_lng'            => 106.7011286,
                'last_location_at'    => now(),
                'last_address'        => 'Pasteur, Phường Sài Gòn, Thành phố Hồ Chí Minh',
                'last_province'       => 'TP.HCM',
                'vehicle_license_plate' => '51C-11111',
                'vehicle_type'          => 'suv',
                'vehicle_seats'         => 7,
            ],
        );

        // Tài xế 2
        $driverUser2 = User::query()->firstOrCreate(
            ['phone' => '1000000002'],
            [
                'name'     => 'Tài xế 1000000002',
                'email'    => 'driver1000000002@appdatxe.test',
                'password' => Hash::make('password'),
                'role'     => 'driver',
                'status'   => 'active',
            ],
        );

        $driverProfile2 = DriverProfile::query()->firstOrCreate(
            ['user_id' => $driverUser2->id],
            [
                'operator_id'         => $admin->id,
                'license_number'      => '10000000021',
                'license_class'       => 'D',
                'license_expiry'      => now()->addYears(3)->toDateString(),
                'experience_years'    => 5,
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => 'off_duty',
                'last_lat'            => 10.7795000,
                'last_lng'            => 106.6990000,
                'last_location_at'    => now(),
                'last_address'        => 'Pasteur, Phường Sài Gòn, Thành phố Hồ Chí Minh',
                'last_province'       => 'TP.HCM',
                'vehicle_license_plate' => '51D-22222',
                'vehicle_type'          => 'limousine',
                'vehicle_seats'         => 16,
            ],
        );

        $driverProfile->update([
            'vehicle_license_plate' => '51C-11111',
            'vehicle_type'          => 'suv',
            'vehicle_seats'         => 7,
            'approval_status'       => 'approved',
            'status'                => 'active',
        ]);
        $driverProfile2->update([
            'vehicle_license_plate' => '51D-22222',
            'vehicle_type'          => 'limousine',
            'vehicle_seats'         => 16,
            'approval_status'       => 'approved',
            'status'                => 'active',
        ]);

        app(DriverCatalogService::class)->syncAllApprovedDrivers();

        Vehicle::query()
            ->whereNotIn('license_plate', ['51C-11111', '51D-22222'])
            ->update(['status' => 'inactive']);

        ScheduleTemplate::query()
            ->whereNull('driver_id')
            ->orWhereNotIn('driver_id', [$driverUser->id, $driverUser2->id])
            ->update(['status' => 'inactive']);
    }
}
