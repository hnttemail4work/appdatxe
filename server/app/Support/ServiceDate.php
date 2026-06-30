<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/** Ngày chạy chuyến — luôn parse theo timezone app, tránh lệch ngày. */
class ServiceDate
{
    public static function today(): string
    {
        return now()->toDateString();
    }

    public static function parse(string $value): Carbon
    {
        $raw = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            throw new InvalidArgumentException('Ngày chạy không hợp lệ.');
        }

        return Carbon::createFromFormat('Y-m-d', $raw, config('app.timezone'))->startOfDay();
    }

    /** Carbon từ cột date/datetime hoặc chuỗi Y-m-d — dùng khi đọc từ Eloquent. */
    public static function dayStart(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone(config('app.timezone'))->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->timezone(config('app.timezone'))->startOfDay();
        }

        return self::parse(trim((string) $value));
    }

    public static function parseOrToday(?string $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            $date = self::parse($value);

            return $date->toDateString() >= self::today()
                ? $date
                : self::parse(self::today());
        }

        return self::parse(self::today());
    }
}
