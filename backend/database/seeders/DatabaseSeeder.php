<?php

namespace Database\Seeders;

use App\Models\MerchantProfile;
use App\Models\PlatformSetting;
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
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'phone' => '0900000000',
                'role' => 'admin',
                'status' => 'active',
            ],
        );

        $operator = User::query()->firstOrCreate(
            ['email' => 'operator@appdatxe.test'],
            [
                'name' => 'Operator A',
                'password' => Hash::make('password'),
                'phone' => '0900000001',
                'role' => 'operator',
                'status' => 'active',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'customer@appdatxe.test'],
            [
                'name' => 'Customer A',
                'password' => Hash::make('password'),
                'phone' => '0900000002',
                'role' => 'customer',
                'status' => 'active',
            ],
        );

        MerchantProfile::query()->firstOrCreate(
            ['user_id' => $operator->id],
            [
                'company_name' => 'AppDatXe Transport',
                'tax_code' => '0123456789',
                'kyc_status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        PlatformSetting::setValue('commission_percentage', ['value' => 10], 'finance');
        PlatformSetting::setValue('deposit_percentage', ['value' => 30], 'finance');

        $routes = [
            ['departure' => 'TP.HCM', 'destination' => 'Vung Tau', 'base_price' => 200000],
            ['departure' => 'TP.HCM', 'destination' => 'Da Lat', 'base_price' => 350000],
            ['departure' => 'TP.HCM', 'destination' => 'Mui Ne', 'base_price' => 280000],
        ];

        foreach ($routes as $routeData) {
            TripRoute::query()->firstOrCreate(
                ['departure' => $routeData['departure'], 'destination' => $routeData['destination']],
                ['base_price' => $routeData['base_price'], 'is_active' => true],
            );
        }

        Vehicle::query()->firstOrCreate(
            ['license_plate' => '51A-12345'],
            [
                'operator_id' => $operator->id,
                'type' => 'limousine',
                'capacity' => 9,
                'status' => 'active',
            ],
        );
    }
}
