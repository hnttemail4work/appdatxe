<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\PushAudience;
use PHPUnit\Framework\TestCase;

class PushAudienceTest extends TestCase
{
    public function test_guest_when_not_authenticated(): void
    {
        $this->assertSame(PushAudience::GUEST, PushAudience::resolve(null));
        $this->assertTrue(PushAudience::enabledFor(null));
    }

    public function test_driver_when_driver_role(): void
    {
        $user = new User(['role' => 'driver']);
        $this->assertSame(PushAudience::DRIVER, PushAudience::resolve($user));
        $this->assertTrue(PushAudience::enabledFor($user));
    }

    public function test_admin_disabled_for_pwa(): void
    {
        $user = new User(['role' => 'admin']);
        $this->assertFalse(PushAudience::enabledFor($user));
        $this->assertSame(PushAudience::GUEST, PushAudience::resolve($user));
    }

    public function test_start_urls_per_role(): void
    {
        $this->assertSame('/', PushAudience::startUrl(PushAudience::GUEST));
        $this->assertSame('/driver/dashboard', PushAudience::startUrl(PushAudience::DRIVER));
    }
}
