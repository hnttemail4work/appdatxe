<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_group',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::query()->where('setting_key', $key)->first();

            return $setting?->setting_value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, mixed $value, ?string $group = null): self
    {
        return static::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'setting_group' => $group],
        );
    }
}
