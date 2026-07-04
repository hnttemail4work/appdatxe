<?php

namespace App\Support;

use Carbon\Carbon;

class DepartureTimeDisplay
{
    /** Chuẩn hóa giờ lưu DB / so khớp lịch (H:i). */
    public static function normalizeForClock(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }

        $raw = trim((string) $time);
        if ($raw === '') {
            return '00:00';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            $hour = min(23, max(0, (int) $m[1]));
            $minute = min(59, max(0, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '00:00';
    }

    /** Giờ hiển thị — ví dụ «06:00». */
    public static function label(mixed $time): string
    {
        if ($time === null || trim((string) $time) === '') {
            return 'Tự chọn';
        }

        return self::normalizeForClock($time);
    }

    /** @return string H:i:s cho cột time trong DB */
    public static function storageValue(string $input): string
    {
        return self::normalizeForClock($input) . ':00';
    }
}
