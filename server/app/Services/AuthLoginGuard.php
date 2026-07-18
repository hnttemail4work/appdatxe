<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

class AuthLoginGuard
{
    public const MAX_FAILURES = 5;

    public const LOCK_MINUTES = 15;

    public function isLocked(User $user): bool
    {
        $until = $user->login_locked_until;
        if (! $until instanceof CarbonInterface) {
            return false;
        }

        if ($until->isFuture()) {
            return true;
        }

        if ((int) $user->login_fail_count > 0 || $user->login_locked_until !== null) {
            $user->forceFill([
                'login_fail_count'    => 0,
                'login_locked_until'  => null,
            ])->save();
        }

        return false;
    }

    public function lockMessage(User $user): string
    {
        $until = $user->login_locked_until;
        $mins = $until ? max(1, now()->diffInMinutes($until, false)) : self::LOCK_MINUTES;

        return 'Tài khoản tạm khóa do nhập sai mật khẩu quá nhiều lần. Thử lại sau khoảng '.$mins.' phút.';
    }

    public function recordFailure(User $user): void
    {
        $count = (int) $user->login_fail_count + 1;
        $updates = ['login_fail_count' => $count];

        if ($count >= self::MAX_FAILURES) {
            $updates['login_locked_until'] = now()->addMinutes(self::LOCK_MINUTES);
        }

        $user->forceFill($updates)->save();
    }

    public function clearFailures(User $user): void
    {
        if ((int) $user->login_fail_count === 0 && $user->login_locked_until === null) {
            return;
        }

        $user->forceFill([
            'login_fail_count'   => 0,
            'login_locked_until' => null,
        ])->save();
    }

    public function remainingAttempts(User $user): int
    {
        return max(0, self::MAX_FAILURES - (int) $user->login_fail_count);
    }
}
