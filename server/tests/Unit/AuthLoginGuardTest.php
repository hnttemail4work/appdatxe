<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthLoginGuard;
use Tests\TestCase;

class AuthLoginGuardTest extends TestCase
{
    private function stubUser(array $attrs = []): User
    {
        $user = new class extends User
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $user->exists = true;
        $user->forceFill(array_merge([
            'login_fail_count'   => 0,
            'login_locked_until' => null,
        ], $attrs));

        return $user;
    }

    public function test_locks_after_five_failures(): void
    {
        $user = $this->stubUser();
        $guard = new AuthLoginGuard();

        for ($i = 0; $i < 4; $i++) {
            $guard->recordFailure($user);
            $this->assertFalse($guard->isLocked($user));
        }

        $guard->recordFailure($user);
        $this->assertTrue($guard->isLocked($user));
        $this->assertSame(5, (int) $user->login_fail_count);
        $this->assertNotNull($user->login_locked_until);
        $this->assertSame(0, $guard->remainingAttempts($user));
    }

    public function test_clear_failures_resets_lock(): void
    {
        $user = $this->stubUser([
            'login_fail_count'   => 5,
            'login_locked_until' => now()->addMinutes(10),
        ]);
        $guard = new AuthLoginGuard();

        $this->assertTrue($guard->isLocked($user));
        $guard->clearFailures($user);

        $this->assertSame(0, (int) $user->login_fail_count);
        $this->assertNull($user->login_locked_until);
        $this->assertFalse($guard->isLocked($user));
    }

    public function test_expired_lock_is_cleared_on_check(): void
    {
        $user = $this->stubUser([
            'login_fail_count'   => 5,
            'login_locked_until' => now()->subMinute(),
        ]);
        $guard = new AuthLoginGuard();

        $this->assertFalse($guard->isLocked($user));
        $this->assertSame(0, (int) $user->login_fail_count);
    }
}
