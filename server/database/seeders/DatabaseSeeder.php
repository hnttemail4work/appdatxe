<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\MerchantProfile;
use App\Models\PlatformSetting;
use App\Models\ScheduleTemplate;
use App\Services\ScheduleLifecycleService;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@appdatxe.test'],
            [
                'name'     => 'Super Admin',
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

        User::query()->firstOrCreate(
            ['email' => 'customer@appdatxe.test'],
            [
                'name'     => 'Khách Hàng A',
                'password' => Hash::make('password'),
                'phone'    => '0900000002',
                'role'     => 'customer',
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

        PlatformSetting::setValue('commission_percentage', ['value' => 10], 'finance');

        // Routes — tên tỉnh khớp chính xác với danh sách provinces trong view
        $routeData = [
            ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu', 'base_price' => 200000],
            ['departure' => 'TP.HCM', 'destination' => 'Đà Lạt',   'base_price' => 350000],
            ['departure' => 'TP.HCM', 'destination' => 'Mũi Né',   'base_price' => 280000],
            ['departure' => 'TP.HCM', 'destination' => 'Đà Nẵng',  'base_price' => 450000],
            ['departure' => 'Hà Nội', 'destination' => 'Đà Nẵng',  'base_price' => 380000],
        ];

        foreach ($routeData as $r) {
            TripRoute::query()->firstOrCreate(
                ['departure' => $r['departure'], 'destination' => $r['destination']],
                ['base_price' => $r['base_price'], 'is_active' => true],
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
                'availability_status' => 'available',
            ],
        );

        // Tài xế 2
        $driverUser2 = User::query()->firstOrCreate(
            ['email' => 'driver2@appdatxe.test'],
            [
                'name'     => 'Trần Thị Lái',
                'password' => Hash::make('password'),
                'phone'    => '0900000004',
                'role'     => 'driver',
                'status'   => 'active',
            ],
        );

        $driverProfile2 = DriverProfile::query()->firstOrCreate(
            ['user_id' => $driverUser2->id],
            [
                'operator_id'         => $operator->id,
                'license_number'      => '98765432100',
                'license_class'       => 'B2',
                'license_expiry'      => now()->addYears(2)->toDateString(),
                'experience_years'    => 5,
                'status'              => 'active',
                'availability_status' => 'available',
            ],
        );

        // Chuyến chạy hằng ngày (template)
        $routes = TripRoute::all()->keyBy(fn ($r) => $r->departure . '|' . $r->destination);

        $templateSeed = [
            ['dep' => 'TP.HCM', 'dst' => 'Vũng Tàu', 'hours' => [7, 13, 18], 'vehicle' => $vehicle,  'driver' => $driverProfile],
            ['dep' => 'TP.HCM', 'dst' => 'Đà Lạt',   'hours' => [20],        'vehicle' => $vehicle,  'driver' => $driverProfile],
            ['dep' => 'TP.HCM', 'dst' => 'Mũi Né',   'hours' => [8, 15],     'vehicle' => $vehicle2, 'driver' => $driverProfile2],
        ];

        foreach ($templateSeed as $seed) {
            $route = $routes->get($seed['dep'] . '|' . $seed['dst']);
            if (! $route) {
                continue;
            }
            foreach ($seed['hours'] as $hour) {
                ScheduleTemplate::query()->firstOrCreate(
                    [
                        'route_id'       => $route->id,
                        'vehicle_id'     => $seed['vehicle']->id,
                        'departure_time' => sprintf('%02d:00:00', $hour),
                    ],
                    [
                        'driver_id'    => $seed['driver']->user_id,
                        'driver_name'  => User::find($seed['driver']->user_id)?->name ?? 'Chưa phân công',
                        'status'       => 'active',
                    ],
                );
            }
        }

        app(ScheduleLifecycleService::class)->sync();
    }
}
