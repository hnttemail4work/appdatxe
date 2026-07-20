<?php

namespace App\Support;

/** Chuẩn hoá chuỗi tìm địa chỉ trước khi gọi Goong (vd. khu phố → kp). */
final class AddressQueryNormalizer
{
    public static function normalize(string $query): string
    {
        $input = trim($query);
        if ($input === '') {
            return '';
        }

        // "khu phố" / "khu pho" (kể cả khoảng trắng thừa) → "kp"
        $normalized = preg_replace(
            '/\bkhu\s*ph[oốớ]\b/iu',
            'kp',
            $input,
        );

        if (! is_string($normalized) || $normalized === '') {
            return $input;
        }

        $normalized = preg_replace('/\s{2,}/u', ' ', $normalized);

        return is_string($normalized) ? trim($normalized) : $input;
    }
}
