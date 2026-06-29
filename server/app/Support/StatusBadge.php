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

    public static function class(string $variant): string
    {
        return 'status-pill status-pill--' . $variant;
    }

    public static function bookingMode(string $mode): string
    {
        return $mode === 'whole_car' ? self::GOLD : self::INFO;
    }

    public static function depositStatus(?string $status): string
    {
        return match ($status) {
            'approved' => self::SUCCESS,
            'rejected' => self::DANGER,
            default    => self::PENDING,
        };
    }

    public static function scheduleDisplay(string $displayStatus, bool $departedIdle = false): string
    {
        if ($departedIdle) {
            return self::NEUTRAL;
        }

        return match ($displayStatus) {
            'running'   => self::GOLD,
            'completed' => self::SUCCESS,
            'cancelled' => self::DANGER,
            default     => self::PENDING,
        };
    }
}
