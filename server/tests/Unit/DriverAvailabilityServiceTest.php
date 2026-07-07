<?php

namespace Tests\Unit;

use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DriverAvailabilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverAvailabilityServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $base = dirname(__DIR__, 2);
        if (is_file($base . '/.env')) {
            foreach (file($base . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                if (str_starts_with(trim($key), 'DB_')) {
                    putenv(trim($key) . '=' . trim($value, " \t\"'"));
                }
            }
            putenv('DB_CONNECTION=mysql');
        }

        $app = require $base . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');

        return $app;
    }

    public function test_reset_for_web_login_clears_availability_and_location(): void
    {
        if (! Schema::hasColumn('driver_profiles', 'last_lat')) {
            $this->markTestSkipped('driver_profiles.last_lat missing');
        }

        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX' . random_int(100, 999),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'last_lat'            => 10.77,
            'last_lng'            => 106.70,
            'last_location_at'    => now(),
        ]);

        app(DriverAvailabilityService::class)->resetForWebLogin($profile);

        $fresh = $profile->fresh();
        $this->assertSame('off_duty', $fresh->availability_status);
        $this->assertNull($fresh->last_lat);
    }

    public function test_web_presence_timeout_marks_off_duty(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX' . random_int(100, 999),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
        ]);

        Cache::forget('driver_web_presence:' . $driver->id);

        $service = app(DriverAvailabilityService::class);
        $service->enforceWebPresenceIdleFor($profile);

        $this->assertSame('off_duty', $profile->fresh()->availability_status);
    }

    public function test_is_bookable_now_requires_available_and_fresh_gps(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX' . random_int(100, 999),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'last_lat'            => 10.77,
            'last_lng'            => 106.70,
            'last_location_at'    => now(),
        ]);

        $service = app(DriverAvailabilityService::class);
        $this->assertTrue($service->isBookableNow($profile));

        $profile->update(['last_location_at' => now()->subMinutes(20)]);
        $this->assertFalse($service->isBookableNow($profile->fresh()));
    }

    public function test_fresh_gps_refreshes_web_presence_instead_of_off_duty(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX' . random_int(100, 999),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'last_lat'            => 10.77,
            'last_lng'            => 106.70,
            'last_location_at'    => now(),
        ]);

        Cache::forget('driver_web_presence:' . $driver->id);

        $service = app(DriverAvailabilityService::class);
        $service->enforceWebPresenceIdleFor($profile);

        $fresh = $profile->fresh();
        $this->assertSame('available', $fresh->availability_status);
        $this->assertTrue(Cache::has('driver_web_presence:' . $driver->id));
        $this->assertTrue($service->isBookableNow($fresh));
    }

    public function test_app_active_for_admin_uses_web_presence_without_fresh_gps(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);
        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TX' . random_int(100, 999),
            'license_number'      => 'L' . random_int(100000, 999999),
            'license_class'       => 'B2',
            'license_expiry'      => now()->addYears(2)->toDateString(),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'last_lat'            => 10.77,
            'last_lng'            => 106.70,
            'last_location_at'    => now()->subMinutes(20),
        ]);

        $service = app(DriverAvailabilityService::class);
        $this->assertFalse($service->isDriverAppActiveForAdmin($profile));

        $service->touchWebPresence((int) $driver->id);
        $this->assertTrue($service->isDriverAppActiveForAdmin($profile->fresh()));
    }
}
