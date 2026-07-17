<?php

namespace App\Support;

/** Danh sách ngân hàng dùng chung form tài xế / admin. */
class BankOptions
{
    /** @return list<string> */
    public static function names(): array
    {
        return [
            'Vietcombank',
            'Techcombank',
            'BIDV',
            'VietinBank',
            'MB Bank',
            'ACB',
            'Sacombank',
            'TPBank',
            'VPBank',
            'Khác',
        ];
    }
}
