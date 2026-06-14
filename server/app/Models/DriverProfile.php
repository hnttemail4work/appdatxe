<?php

namespace App\Models;

use App\Services\DriverPhotoService;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id', 'operator_id', 'driver_code',
        'license_number', 'license_class', 'license_expiry', 'experience_years',
        'status', 'availability_status', 'notes',
        'bank_name', 'bank_account',
        'photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_vehicle', 'photo_vehicles',
    ];

    protected static function booted(): void
    {
        static::created(function (DriverProfile $profile): void {
            if (empty($profile->driver_code)) {
                $profile->updateQuietly([
                    'driver_code' => self::generateDriverCode($profile->id),
                ]);
            }
        });
    }

    public static function generateDriverCode(?int $profileId = null): string
    {
        do {
            $code = 'TX' . str_pad((string) ($profileId ?? random_int(1, 999999)), 6, '0', STR_PAD_LEFT);
            if ($profileId === null) {
                $code = 'TX' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
            }
        } while (self::query()->where('driver_code', $code)->exists());

        return $code;
    }

    public function availabilityLabel(): string
    {
        return match ($this->availability_status ?? 'off_duty') {
            'available' => 'Sẵn sàng',
            'on_trip'   => 'Đang chạy',
            default     => 'Nghỉ / Bận',
        };
    }

    protected function casts(): array
    {
        return [
            'license_expiry'   => 'date',
            'experience_years' => 'integer',
            'photo_vehicles'   => 'array',
        ];
    }

    public function photoUrl(string $column): ?string
    {
        return DriverPhotoService::publicUrl($this->{$column});
    }

    public function vehiclePhotoUrls(): array
    {
        return collect($this->photo_vehicles ?? [])
            ->map(fn ($p) => DriverPhotoService::publicUrl($p))
            ->filter()
            ->values()
            ->all();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'driver_id', 'user_id');
    }
}
