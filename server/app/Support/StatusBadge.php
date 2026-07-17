<?php

namespace App\Support;

/** Nhãn trạng thái — theme vàng–đen (slug → class CSS). */
class StatusBadge
{
    public const GOLD = 'gold';
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const NEUTRAL = 'neutral';
    public const INFO = 'info';
    public const ACCENT = 'accent';

    public static function depositStatus(?string $status): string
    {
        return match ($status) {
            'approved' => self::SUCCESS,
            'rejected' => self::DANGER,
            default    => self::PENDING,
        };
    }
}
