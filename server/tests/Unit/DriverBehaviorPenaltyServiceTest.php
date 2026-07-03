<?php

namespace Tests\Unit;

use App\Models\DriverDailyPenalty;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DriverBehaviorPenaltyService;
use App\Services\DriverMissedTripService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DriverBehaviorPenaltyServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $base = dirname(__DIR__, 2);
        self::applyMysqlEnvFromDotEnv($base);

        $app = require $base . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');

        return $app;
    }

    private static function applyMysqlEnvFromDotEnv(string $base): void
    {
        $envFile = $base . '/.env';
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

    private function seedProfile(): DriverProfile
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);

        return DriverProfile::query()->create([
            'user_id'         => $driver->id,
            'operator_id'     => $operator->id,
            'driver_code'     => 'PB' . random_int(1000, 9999),
            'license_number'  => 'L' . random_int(10000, 99999),
            'license_class'   => 'B2',
            'license_expiry'  => now()->addYear()->toDateString(),
            'status'          => 'active',
            'approval_status' => 'approved',
            'availability_status' => 'available',
        ]);
    }

    public function test_three_consecutive_cancels_lock_driver(): void
    {
        $profile = $this->seedProfile();
        $service = app(DriverBehaviorPenaltyService::class);

        $this->assertFalse($service->recordConsecutiveCancel($profile));
        $this->assertFalse($service->recordConsecutiveCancel($profile->fresh()));
        $this->assertTrue($service->recordConsecutiveCancel($profile->fresh()));

        $fresh = $profile->fresh();
        $this->assertTrue(app(DriverMissedTripService::class)->isLocked($fresh));
        $this->assertSame(3, (int) $fresh->missed_trip_strikes);
    }

    public function test_complete_resets_consecutive_cancel_counter(): void
    {
        $profile = $this->seedProfile();
        $service = app(DriverBehaviorPenaltyService::class);

        $service->recordConsecutiveCancel($profile);
        $service->recordConsecutiveCancel($profile->fresh());
        $service->resetConsecutiveCancel($profile->fresh());

        $row = DriverDailyPenalty::query()
            ->where('driver_profile_id', $profile->id)
            ->whereDate('penalty_date', today())
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->consecutive_cancel_count);
    }

    public function test_two_late_continue_timeouts_block_receiving_for_day(): void
    {
        $profile = $this->seedProfile();
        $service = app(DriverBehaviorPenaltyService::class);

        $this->assertFalse($service->isReceiveBlockedToday($profile));
        $service->recordLateContinueTimeout($profile);
        $this->assertFalse($service->isReceiveBlockedToday($profile->fresh()));
        $service->recordLateContinueTimeout($profile->fresh());
        $this->assertTrue($service->isReceiveBlockedToday($profile->fresh()));
        $this->assertNotNull($service->receiveBlockReason($profile->fresh()));
    }
}
