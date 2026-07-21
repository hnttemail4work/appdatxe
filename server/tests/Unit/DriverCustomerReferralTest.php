<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\DriverCustomer;
use App\Models\DriverProfile;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\ReferralCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverCustomerReferralTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $base = dirname(__DIR__, 2);
        self::applyMysqlEnvFromDotEnv($base);

        $app = require $base.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');

        return $app;
    }

    private static function applyMysqlEnvFromDotEnv(string $base): void
    {
        $envFile = $base.'/.env';
        if (! is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            if (! str_starts_with($key, 'DB_')) {
                continue;
            }
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';
    }

    public function test_can_assign_only_zero_commission_codes(): void
    {
        $hh = new ReferralCode([
            'type'               => ReferralCode::TYPE_REFERRER,
            'status'             => ReferralCode::STATUS_ACTIVE,
            'commission_percent' => 8,
        ]);
        $this->assertFalse($hh->canAssignToDriver());

        $mine = new ReferralCode([
            'type'               => ReferralCode::TYPE_REFERRER,
            'status'             => ReferralCode::STATUS_ACTIVE,
            'commission_percent' => 0,
        ]);
        $this->assertTrue($mine->canAssignToDriver());
    }

    public function test_remember_customer_from_completed_booking_upserts_driver_customer(): void
    {
        if (! Schema::hasTable('driver_customers')) {
            $this->markTestSkipped('Thiếu bảng driver_customers — chạy migrate trước.');
        }

        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => User::factory()->create(['role' => 'operator'])->id,
            'driver_code'         => 'TXDC'.random_int(100, 999),
            'license_number'      => 'L'.random_int(100000, 999999),
            'license_class'       => 'B2',
            'license_expiry'      => now()->addYears(2)->toDateString(),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
        ]);

        $referral = ReferralCode::query()->create([
            'type'                       => ReferralCode::TYPE_REFERRER,
            'name'                       => 'TX QR',
            'phone'                      => '0901000001',
            'status'                     => ReferralCode::STATUS_ACTIVE,
            'commission_percent'         => 0,
            'customer_discount_percent'  => 0,
            'assigned_driver_profile_id' => $profile->id,
            'activated_at'               => now(),
        ]);

        $route = \App\Models\TripRoute::query()->firstOrCreate(
            ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu'],
            ['base_price' => 200000, 'distance_km' => 95, 'is_active' => true],
        );
        $vehicle = \App\Models\Vehicle::query()->create([
            'operator_id'   => $profile->operator_id,
            'license_plate' => '51A-'.random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);
        $schedule = \App\Models\Schedule::query()->create([
            'route_id'       => $route->id,
            'vehicle_id'     => $vehicle->id,
            'driver_id'      => null,
            'driver_name'    => '',
            'departure_time' => now()->addHours(2),
            'service_date'   => now()->toDateString(),
            'status'         => 'scheduled',
            'trip_code'      => 'DC'.strtoupper(substr(uniqid(), -5)),
        ]);

        $booking = Booking::query()->create([
            'contact_phone'            => '0909'.random_int(100000, 999999),
            'passenger_name'           => 'Khách QR',
            'passenger_gender'         => 'male',
            'schedule_id'              => $schedule->id,
            'booking_reference'        => 'BK-DC-'.uniqid(),
            'total_price'              => 150000,
            'payment_status'           => 'unpaid',
            'trip_status'              => 'completed',
            'booking_status'           => 'confirmed',
            'completed_at'             => now(),
            'applied_referral_code_id' => $referral->id,
            'pickup_address'           => 'A',
            'dropoff_address'          => 'B',
        ]);

        app(ReferralCodeService::class)->rememberCustomerFromCompletedBooking($booking->fresh());

        $row = DriverCustomer::query()
            ->where('driver_profile_id', $profile->id)
            ->where('last_booking_id', $booking->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->bookings_count);
        $this->assertSame('Khách QR', $row->passenger_name);

        $preferred = app(ReferralCodeService::class)->preferredDriverProfileForBooking($booking->fresh());
        $this->assertNotNull($preferred);
        $this->assertSame($profile->id, $preferred->id);

        $list = app(ReferralCodeService::class)->listDriverCustomers($profile);
        $this->assertCount(1, $list);
    }
}
