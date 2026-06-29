<?php

namespace App\Support;

use App\Models\PlatformSetting;

class PlatformPaymentInfo
{
    /** @return array{bank_name: string, bank_bin: string, account: string, account_name: string} */
    public static function bank(): array
    {
        $stored = PlatformSetting::getValue('platform_bank', []);
        $config = config('app.platform_bank', []);

        return [
            'bank_name'    => (string) ($stored['bank_name'] ?? $config['bank_name'] ?? ''),
            'bank_bin'     => preg_replace('/\D/', '', (string) ($stored['bank_bin'] ?? $config['bank_bin'] ?? '')),
            'account'      => preg_replace('/\s+/', '', (string) ($stored['account'] ?? $config['account'] ?? '')),
            'account_name' => (string) ($stored['account_name'] ?? $config['account_name'] ?? config('app.name')),
        ];
    }

    public static function isConfigured(): bool
    {
        $bank = self::bank();

        return $bank['bank_bin'] !== '' && $bank['account'] !== '';
    }

    public static function vietQrImageUrl(int $amount = 0, ?string $addInfo = null): ?string
    {
        $bank = self::bank();

        if ($bank['bank_bin'] === '' || $bank['account'] === '') {
            return null;
        }

        $params = [];
        if ($amount > 0) {
            $params['amount'] = (string) $amount;
        }
        if ($addInfo !== null && $addInfo !== '') {
            $params['addInfo'] = $addInfo;
        }
        if ($bank['account_name'] !== '') {
            $params['accountName'] = $bank['account_name'];
        }

        $base = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.jpg',
            $bank['bank_bin'],
            $bank['account'],
        );

        if ($params === []) {
            return $base;
        }

        return $base . '?' . http_build_query($params);
    }

    public static function driverTransferContent(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    public static function transferLabel(int $amount): string
    {
        $bank = self::bank();

        return sprintf(
            '%s, %s, %s',
            $bank['bank_name'] ?: 'Ngân hàng',
            $bank['account'] ?: '—',
            number_format($amount, 0, ',', '.') . ' đ',
        );
    }
}
