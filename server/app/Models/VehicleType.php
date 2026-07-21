<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class VehicleType extends Model
{
    protected $fillable = [
        'key',
        'label',
        'seats',
        'family',
        'price_percent',
        'sort_order',
        'is_active',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'seats'         => 'integer',
            'price_percent' => 'float',
            'sort_order'    => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public const CACHE_KEY = 'vehicle_types.active_v2';

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.all');
        // Keys cũ trước khi bump version.
        Cache::forget('vehicle_types.active_v1');
        Cache::forget('vehicle_types.active_v1.all');
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function activeCached()
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, function () {
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

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function allCached()
    {
        try {
            return Cache::remember(self::CACHE_KEY.'.all', 300, function () {
                return static::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            });
        } catch (\Throwable) {
            return collect();
        }
    }

    public function multiplier(): float
    {
        return max(0.0, (float) $this->price_percent) / 100.0;
    }

    public function imageUrl(): ?string
    {
        return PublicStorageUrl::url($this->image_path);
    }

    public function deleteStoredImage(): void
    {
        if (! $this->image_path) {
            return;
        }
        try {
            Storage::disk('public')->delete($this->image_path);
        } catch (\Throwable) {
        }
        $this->image_path = null;
    }
}
