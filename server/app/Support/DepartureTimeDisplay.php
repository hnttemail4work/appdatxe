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

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(.+)?\s*$/iu', $raw, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $suffixRaw = isset($m[4]) ? trim($m[4]) : '';
            $suffix = $suffixRaw !== '' ? self::normalizePeriodKey($suffixRaw) : null;

            if ($suffix === null) {
                return sprintf('%02d:%02d', min(23, max(0, $hour)), min(59, max(0, $minute)));
            }

            if (in_array($suffix, ['chieu', 'toi'], true) && $hour < 12) {
                $hour += 12;
            }
            if ($suffix === 'dem' && $hour === 12) {
                $hour = 0;
            }
            if ($suffix === 'sang' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', min(23, max(0, $hour)), min(59, max(0, $minute)));
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

        $clock = self::normalizeForClock($time);
        [$hour, $minute] = array_map('intval', explode(':', $clock));

        return sprintf('%02d:%02d', $hour, $minute) . ' ' . self::periodLabelFromHour($hour);
    }

    /** @return string H:i:s cho cột time trong DB */
    public static function storageValue(string $input): string
    {
        return self::normalizeForClock($input) . ':00';
    }

    private static function normalizePeriodKey(string $suffix): ?string
    {
        $key = mb_strtolower(trim($suffix));

        return match ($key) {
            'dem', 'đêm' => 'dem',
            'sa', 'sáng', 'sang' => 'sang',
            'ch', 'chiều', 'chieu', 'pm' => 'chieu',
            'toi', 'tối' => 'toi',
            default => null,
        };
    }

    private static function periodLabelFromHour(int $hour): string
    {
        if ($hour >= 18) {
            return 'tối';
        }

        if ($hour >= 12) {
            return 'chiều';
        }

        if ($hour >= 5) {
            return 'sáng';
        }

        return 'đêm';
    }
}
