<?php

namespace App\Support;

use Carbon\Carbon;

class DepartureTimeDisplay
{
    /** Chuẩn hóa giờ lưu DB / so khớp lịch (H:i hoặc H:i:s). */
    public static function normalizeForClock(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }

        $raw = trim((string) $time);
        if ($raw === '') {
            return '00:00';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(SA|PM)?\s*$/iu', $raw, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $suffix = isset($m[4]) ? strtoupper($m[4]) : null;

            if ($suffix === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($suffix === 'SA' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', $hour, $minute);
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $raw)) {
            return substr($raw, 0, 5);
        }

        return substr($raw, 0, 5);
    }

    /** Giờ hiển thị cho khách, ví dụ «06:00 sáng». */
    public static function label(mixed $time): string
    {
        if ($time === null || trim((string) $time) === '') {
            return 'Tự chọn';
        }

        $raw = is_string($time) ? trim($time) : null;
        $hour = null;
        $minute = null;
        $suffix = null;

        if ($raw !== null && $raw !== '' && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(SA|PM)?\s*$/iu', $raw, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $suffix = isset($m[3]) && $m[3] !== '' ? strtoupper($m[3]) : null;
        } else {
            $clock = self::normalizeForClock($time);
            [$hour, $minute] = array_map('intval', explode(':', $clock));
        }

        $clock = sprintf('%02d:%02d', $hour, $minute);

        return $clock . ' ' . self::periodLabel($hour, $suffix);
    }

    /** @return string H:i:s cho cột time trong DB */
    public static function storageValue(string $input): string
    {
        return self::normalizeForClock($input) . ':00';
    }

    private static function periodLabel(int $hour, ?string $suffix): string
    {
        if ($suffix === 'SA') {
            return 'sáng';
        }

        if ($suffix === 'PM') {
            return $hour >= 18 ? 'tối' : 'chiều';
        }

        if ($hour >= 18) {
            return 'tối';
        }

        if ($hour >= 12) {
            return 'chiều';
        }

        return 'sáng';
    }
}
