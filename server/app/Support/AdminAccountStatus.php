<?php

namespace App\Support;

/**
 * Trạng thái tài khoản admin.
 * TX: Đang hoạt động ↔ Tạm ngưng
 * KH: Hoạt động ↔ Tạm ngưng
 * `inactive` legacy được coi như tạm ngưng khi hiển thị / mở lại.
 */
final class AdminAccountStatus
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public static function isRunning(?string $status): bool
    {
        return $status === self::ACTIVE;
    }

    public static function isPaused(?string $status): bool
    {
        return ! self::isRunning($status);
    }

    /** @param  'driver'|'customer'  $audience */
    public static function label(?string $status, string $audience = 'driver'): string
    {
        if (! self::isRunning($status)) {
            return 'Tạm ngưng';
        }

        return $audience === 'customer' ? 'Hoạt động' : 'Đang hoạt động';
    }

    public static function color(?string $status): string
    {
        return self::isRunning($status) ? StatusBadge::ACCENT : StatusBadge::DANGER;
    }
}

