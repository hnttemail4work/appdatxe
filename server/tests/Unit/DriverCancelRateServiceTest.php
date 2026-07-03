<?php

namespace Tests\Unit;

use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DriverCancelRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverCancelRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_offer_and_reject_updates_percent(): void
    {
        $profile = $this->makeDriverProfile();

        $service = app(DriverCancelRateService::class);
        $service->recordOfferForUserId((int) $profile->user_id);
        $service->recordOfferForUserId((int) $profile->user_id);
        $service->recordRejectForUserId((int) $profile->user_id);

        $fresh = $profile->fresh();
        $this->assertSame(2, (int) $fresh->cuoc_offer_count);
        $this->assertSame(1, (int) $fresh->cuoc_reject_count);
        $this->assertSame(50.0, (float) $fresh->cancel_rate_percent);
        $this->assertSame('50,0%', $fresh->cancelRateLabel());
    }

    public function test_reset_clears_cancel_rate(): void
    {
        $profile = $this->makeDriverProfile();
        $service = app(DriverCancelRateService::class);

        $service->recordOfferForUserId((int) $profile->user_id);
        $service->recordRejectForUserId((int) $profile->user_id);
        $service->reset($profile->fresh());

        $fresh = $profile->fresh();
        $this->assertSame(0, (int) $fresh->cuoc_offer_count);
        $this->assertSame(0, (int) $fresh->cuoc_reject_count);
        $this->assertSame(0.0, (float) $fresh->cancel_rate_percent);
        $this->assertFalse($fresh->hasCancelRate());
    }

    private function makeDriverProfile(): DriverProfile
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $driver = User::factory()->create(['role' => 'driver']);

        return DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'license_number'      => 'LIC' . random_int(10000, 99999),
            'license_class'       => 'D',
            'license_expiry'      => now()->addYear()->toDateString(),
            'experience_years'    => 3,
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'off_duty',
        ]);
    }
}
