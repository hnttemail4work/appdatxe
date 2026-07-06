<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Gợi ý tên địa danh phổ biến — bổ sung khi Nominatim không khớp cách gõ của người dùng. */
final class GeocodeSearchAliases
{
    /** @return list<string> */
    public static function variants(string $query, string $province = ''): array
    {
        $text = trim($query);
        if ($text === '') {
            return [];
        }

        $variants = [];
        $folded = mb_strtolower(trim(preg_replace('/\s+/u', ' ', Str::ascii($text)) ?? ''));

        foreach (self::hcmDistrictAliases() as $needle => $label) {
            if ($folded === $needle || str_contains($folded, $needle)) {
                $variants[] = self::withProvinceContext($label, $province);
            }
        }

        foreach (self::hcmWardAliases() as $needle => $label) {
            if ($folded === $needle || str_contains($folded, $needle)) {
                $variants[] = self::withProvinceContext($label, $province);
            }
        }

        if (preg_match('/\b(sai gon|saigon|tp hcm|tphcm|hcm)\b/u', $folded)) {
            $stripped = trim(preg_replace('/\b(sai gon|saigon|tp hcm|tphcm|hcm)\b/u', '', $folded) ?? '');
            if ($stripped !== '') {
                $variants[] = self::withProvinceContext($stripped, 'TP.HCM');
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private static function withProvinceContext(string $label, string $province): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        if ($province === 'TP.HCM' || $province === '') {
            $lower = mb_strtolower($label);
            if (! str_contains($lower, 'hồ chí minh') && ! str_contains($lower, 'ho chi minh')) {
                return $label.', Thành phố Hồ Chí Minh';
            }
        }

        return $label;
    }

    /** @return array<string, string> */
    private static function hcmDistrictAliases(): array
    {
        return [
            'q1' => 'Quận 1',
            'q 1' => 'Quận 1',
            'quan 1' => 'Quận 1',
            'q2' => 'Quận 2',
            'quan 2' => 'Quận 2',
            'q3' => 'Quận 3',
            'quan 3' => 'Quận 3',
            'q4' => 'Quận 4',
            'quan 4' => 'Quận 4',
            'q5' => 'Quận 5',
            'quan 5' => 'Quận 5',
            'q6' => 'Quận 6',
            'quan 6' => 'Quận 6',
            'q7' => 'Quận 7',
            'quan 7' => 'Quận 7',
            'q8' => 'Quận 8',
            'quan 8' => 'Quận 8',
            'q9' => 'Quận 9',
            'quan 9' => 'Quận 9',
            'q10' => 'Quận 10',
            'quan 10' => 'Quận 10',
            'q11' => 'Quận 11',
            'quan 11' => 'Quận 11',
            'q12' => 'Quận 12',
            'quan 12' => 'Quận 12',
            'binh tan' => 'Quận Bình Tân',
            'binh thanh' => 'Quận Bình Thạnh',
            'go vap' => 'Quận Gò Vấp',
            'phu nhuan' => 'Quận Phú Nhuận',
            'tan binh' => 'Quận Tân Bình',
            'tan phu' => 'Quận Tân Phú',
            'thu duc' => 'Thành phố Thủ Đức',
            'huyen binh chanh' => 'Huyện Bình Chánh',
            'huyen cu chi' => 'Huyện Củ Chi',
            'huyen hoc mon' => 'Huyện Hóc Môn',
            'huyen nha be' => 'Huyện Nhà Bè',
            'huyen can gio' => 'Huyện Cần Giờ',
        ];
    }

    /** @return array<string, string> */
    private static function hcmWardAliases(): array
    {
        return [
            'tan son nhi' => 'Phường Tân Sơn Nhì',
            'tan son nhat' => 'Phường Tân Sơn Nhất',
            'tan hung' => 'Phường Tân Hưng',
            'tan my' => 'Phường Tân Mỹ',
            'an lac' => 'Phường An Lạc',
            'binh tri dong' => 'Phường Bình Trị Đông',
            'binh hung' => 'Phường Bình Hưng',
            'phuoc long' => 'Phường Phước Long',
            'hiep binh chanh' => 'Phường Hiệp Bình Chánh',
            'linh trung' => 'Phường Linh Trung',
            'linh tay' => 'Phường Linh Tây',
            'linh xuan' => 'Phường Linh Xuân',
            'thao dien' => 'Phường Thảo Điền',
            'an phu' => 'Phường An Phú',
        ];
    }
}
