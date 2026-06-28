<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Tài khoản công ty nhận phí nền tảng — hiển thị QR cho tài xế chuyển khoản. */
class PlatformPaymentInfo
{
    /** @return array{bank_name: string, bank_bin: string, account: string, account_name: string} */
    public static function bank(): array
    {
        $stored = PlatformSetting::getValue('platform_bank', []);
        $config = config('app.platform_bank', []);

        return [
            'bank_name'    => (string) ($stored['bank_name'] ?? $config['bank_name'] ?? ''),
            'bank_bin'     => (string) ($stored['bank_bin'] ?? $config['bank_bin'] ?? ''),
            'account'      => preg_replace('/\s+/', '', (string) ($stored['account'] ?? $config['account'] ?? '')),
            'account_name' => (string) ($stored['account_name'] ?? $config['account_name'] ?? config('app.name')),
        ];
    }

    public static function isConfigured(): bool
    {
        $bank = self::bank();

        return $bank['bank_bin'] !== '' && $bank['account'] !== '';
    }

    public static function driverTransferContent(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    public static function vietQrImageUrl(int $amount, ?string $addInfo = null): ?string
    {
        if (! self::isConfigured()) {
            return null;
        }

        $bank = self::bank();
        $query = http_build_query(array_filter([
            'amount'      => max($amount, 0),
            'addInfo'     => $addInfo,
            'accountName' => $bank['account_name'],
        ], fn ($v) => $v !== null && $v !== ''));

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.jpg?%s',
            $bank['bank_bin'],
            $bank['account'],
            $query,
        );
    }

    public static function transferLabel(int $amount): string
    {
        $bank = self::bank();

        return sprintf(
            '%s · %s · %s',
            $bank['bank_name'] ?: 'Ngân hàng',
            $bank['account'] ?: '—',
            number_format($amount, 0, ',', '.') . ' đ',
        );
    }
}
