<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PricingToll extends Model
{
    protected $fillable = [
        'from_province',
        'to_province',
        'amount_vnd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public const CACHE_KEY = 'pricing_tolls.active_v1';

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function activeCached()
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, function () {
                return static::query()
                    ->where('is_active', true)
                    ->orderBy('from_province')
                    ->orderBy('to_province')
                    ->get();
            });
        } catch (\Throwable) {
            return collect();
        }
    }

    public static function amountFor(string $from, string $to): int
    {
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }

        foreach (self::activeCached() as $row) {
            if (
                ($row->from_province === $from && $row->to_province === $to)
                || ($row->from_province === $to && $row->to_province === $from)
            ) {
                return max(0, (int) $row->amount_vnd);
            }
        }

        return 0;
    }
}
