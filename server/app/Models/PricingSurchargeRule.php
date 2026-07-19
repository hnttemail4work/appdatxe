<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PricingSurchargeRule extends Model
{
    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_PEAK = 'peak';

    public const TYPE_RAIN = 'rain';

    public const MODE_PERCENT = 'percent';

    public const MODE_FIXED = 'fixed';

    protected $fillable = [
        'type',
        'name',
        'mode',
        'value',
        'payload',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value'     => 'float',
            'payload'   => 'array',
            'is_active' => 'boolean',
            'sort_order'=> 'integer',
        ];
    }

    public const CACHE_KEY = 'pricing_surcharge_rules.active_v1';

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function activeCached()
    {
        try {
            return Cache::remember(self::CACHE_KEY, 120, function () {
                return static::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            });
        } catch (\Throwable) {
            return collect();
        }
    }

    public function isPercent(): bool
    {
        return $this->mode === self::MODE_PERCENT;
    }
}
