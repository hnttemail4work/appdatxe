<?php

namespace App\Support;

/**
 * Parse chuỗi QR CCCD Việt Nam (pipe-separated) → họ tên / ngày sinh / giới tính / số CCCD.
 * Dùng chung khi admin duyệt khách & tài xế.
 */
final class CccdQrParser
{
    /**
     * @return array{id_number: ?string, name: ?string, date_of_birth: ?string, gender: ?string}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Một số máy quét thêm prefix URL / text — lấy đoạn có nhiều dấu |.
        if (! str_contains($raw, '|') && preg_match('/\d{9,12}.+\|.+/u', $raw, $m)) {
            $raw = $m[0];
        }

        $parts = array_map(static fn (string $p): string => trim($p), explode('|', $raw));
        if (count($parts) < 5) {
            return null;
        }

        $idNumber = self::digitsOnly($parts[0] ?? '');
        if (strlen($idNumber) < 9) {
            $idNumber = null;
        }

        $name = self::normalizeName($parts[2] ?? '');
        $dob = self::parseDob($parts[3] ?? '');
        $gender = self::parseGender($parts[4] ?? '');

        if ($name === null && $dob === null && $gender === null && $idNumber === null) {
            return null;
        }

        return [
            'id_number'     => $idNumber,
            'name'          => $name,
            'date_of_birth' => $dob,
            'gender'        => $gender,
        ];
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private static function normalizeName(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '' || preg_match('/^\d+$/', $value)) {
            return null;
        }

        // Title-case nhẹ cho tên viết HOA từ CCCD
        if (mb_strtoupper($value, 'UTF-8') === $value) {
            $value = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        return $value;
    }

    private static function parseDob(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $m)) {
            return self::ymd($m[3], $m[2], $m[1]);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return self::ymd($m[1], $m[2], $m[3]);
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $m)) {
            return self::ymd($m[3], $m[2], $m[1]);
        }

        return null;
    }

    private static function ymd(string $y, string $m, string $d): ?string
    {
        $y = (int) $y;
        $m = (int) $m;
        $d = (int) $d;
        if (! checkdate($m, $d, $y) || $y < 1900) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private static function parseGender(string $value): ?string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'nữ') || str_contains($value, 'nu') || $value === 'female' || $value === 'f') {
            return 'female';
        }

        if (str_contains($value, 'nam') || $value === 'male' || $value === 'm') {
            return 'male';
        }

        return null;
    }
}
