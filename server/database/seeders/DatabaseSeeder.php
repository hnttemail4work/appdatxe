<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\MerchantProfile;
use App\Models\PlatformSetting;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripPricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@appdatxe.test'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'phone'    => '0900000000',
                'role'     => 'admin',
                'status'   => 'active',
            ],
        );

        $operator = User::query()->firstOrCreate(
            ['email' => 'vantam.quanly@gmail.com'],
            [
                'name'     => 'Nguyễn Văn Tâm',
                'password' => Hash::make('password'),
                'phone'    => '0900000001',
                'role'     => 'operator',
                'status'   => 'active',
            ],
        );

        MerchantProfile::query()->firstOrCreate(
            ['user_id' => $operator->id],
            [
                'company_name' => 'Tam Long Limo',
                'tax_code'     => '0123456789',
                'kyc_status'   => 'approved',
                'approved_by'  => $admin->id,
                'approved_at'  => now(),
            ],
        );

        PlatformSetting::setValue('commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('app_commission_percentage', ['value' => 2], 'finance');
        PlatformSetting::setValue('round_trip_discount_percentage', ['value' => 15], 'finance');

        PlatformSetting::setValue('platform_bank', [
            'bank_name'    => 'Vietcombank',
            'bank_bin'     => '970436',
            'account'      => '0123456789',
            'account_name' => 'TAM LONG LIMO',
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

        $vehicle = Vehicle::query()->firstOrCreate(
            ['license_plate' => '51A-12345'],
            [
                'operator_id' => $operator->id,
                'type'        => 'limousine',
                'capacity'    => 9,
                'status'      => 'active',
            ],
        );

        $vehicle2 = Vehicle::query()->firstOrCreate(
            ['license_plate' => '51B-67890'],
            [
                'operator_id' => $operator->id,
                'type'        => 'sedan',
                'capacity'    => 4,
                'status'      => 'active',
            ],
        );

        $vehicle7 = Vehicle::query()->firstOrCreate(
            ['license_plate' => '51C-11111'],
            [
                'operator_id' => $operator->id,
                'type'        => 'suv',
                'capacity'    => 7,
                'status'      => 'active',
            ],
        );

        $vehicle16 = Vehicle::query()->firstOrCreate(
            ['license_plate' => '51D-22222'],
            [
                'operator_id' => $operator->id,
                'type'        => 'limousine',
                'capacity'    => 16,
                'status'      => 'active',
            ],
        );

        // Tài xế 1
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
                'operator_id'         => $operator->id,
                'license_number'      => '12345678901',
                'license_class'       => 'D',
                'license_expiry'      => now()->addYears(3)->toDateString(),
                'experience_years'    => 8,
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => 'available',
            ],
        );

        // Chỉ 2 chuyến mẫu — quản lý tự tạo thêm trên dashboard
        $routes = TripRoute::query()
            ->whereIn('departure', ['TP.HCM'])
            ->whereIn('destination', ['Vũng Tàu', 'Đà Lạt'])
            ->get()
            ->keyBy(fn ($r) => $r->departure . '|' . $r->destination);

        $sampleTemplates = [
            'TP.HCM|Vũng Tàu' => [
                ['vehicle' => $vehicle7, 'time' => '06:00:00'],
            ],
            'TP.HCM|Đà Lạt' => [
                ['vehicle' => $vehicle16, 'time' => '20:00:00'],
            ],
        ];

        $pricing = app(TripPricingService::class);

        foreach ($sampleTemplates as $routeKey => $entries) {
            if (! $routes->has($routeKey)) {
                continue;
            }
            foreach ($entries as $entry) {
                $capacity = (int) $entry['vehicle']->capacity;
                $wholeCar = $pricing->defaultWholeCarPrice($capacity);
                $seatPrice = $pricing->sharedSeatFromWholeCar($wholeCar);

                ScheduleTemplate::query()->updateOrCreate(
                    [
                        'route_id'       => $routes[$routeKey]->id,
                        'vehicle_id'     => $entry['vehicle']->id,
                        'departure_time' => $entry['time'],
                    ],
                    [
                        'driver_id'       => null,
                        'driver_name'     => 'Chờ khách đặt',
                        'whole_car_price' => $wholeCar,
                        'seat_price'      => $seatPrice,
                        'status'          => 'active',
                    ],
                );
            }
        }
    }
}
