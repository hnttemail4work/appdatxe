<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DriverWalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateDriver1000000002Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['phone' => '1000000002'],
            [
                'name'     => 'Tài xế 1000000002',
                'email'    => 'driver1000000002@appdatxe.test',
                'password' => Hash::make('password'),
                'role'     => 'driver',
                'status'   => 'active',
            ],
        );

        $profile = DriverProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'operator_id'         => $admin->id,
                'license_number'      => '10000000021',
                'license_class'       => 'D',
                'license_expiry'      => now()->addYears(3)->toDateString(),
                'experience_years'    => 5,
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => 'off_duty',
            ],
        );

        app(DriverWalletService::class)->walletFor($profile);
    }
}
