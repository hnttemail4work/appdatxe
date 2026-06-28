<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Điểm đi/đến quanh TP.HCM — tỉnh lân cận và khu vực Nam Bộ hay đi. */
class SouthernProvinces
{
    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        return [
            'Trung tâm' => [
                'TP.HCM',
            ],
            'Lân cận TP.HCM' => [
                'Bình Dương',
                'Đồng Nai',
                'Long An',
                'Tây Ninh',
            ],
            'Ven biển & du lịch gần' => [
                'Vũng Tàu',
                'Bà Rịa',
                'Phan Thiết',
                'Mũi Né',
                'Đà Lạt',
            ],
            'Đồng bằng sông Cửu Long' => [
                'Mỹ Tho',
                'Bến Tre',
                'Vĩnh Long',
                'Cần Thơ',
                'Long Xuyên',
                'Châu Đốc',
            ],
        ];
    }

    /** Khoảng cách ước tính từ TP.HCM (km). */
    public static function distanceFromHub(string $city): int
    {
        return match ($city) {
            'TP.HCM'       => 0,
            'Bình Dương'   => 25,
            'Đồng Nai'     => 35,
            'Long An'      => 45,
            'Tây Ninh'     => 95,
            'Vũng Tàu'     => 95,
            'Bà Rịa'       => 85,
            'Phan Thiết'   => 200,
            'Mũi Né'       => 220,
            'Đà Lạt'       => 310,
            'Mỹ Tho'       => 70,
            'Bến Tre'      => 85,
            'Vĩnh Long'    => 130,
            'Cần Thơ'      => 170,
            'Long Xuyên'   => 195,
            'Châu Đốc'     => 250,
            default        => 0,
        };
    }

    public static function distanceBetween(string $from, string $to): int
    {
        $from = trim($from);
        $to = trim($to);

        if ($from === $to) {
            return 0;
        }

        if ($from === 'TP.HCM') {
            return self::distanceFromHub($to);
        }

        if ($to === 'TP.HCM') {
            return self::distanceFromHub($from);
        }

        return self::distanceFromHub($from) + self::distanceFromHub($to);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    public static function isAllowed(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && in_array($value, self::all(), true);
    }

    /** @return \Illuminate\Validation\Rules\In */
    public static function inRule()
    {
        return Rule::in(self::all());
    }
}
