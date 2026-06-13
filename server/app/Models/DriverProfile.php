<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id', 'operator_id',
        'license_number', 'license_class', 'license_expiry', 'experience_years',
        'status', 'availability_status', 'notes',
        'photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_vehicle', 'photo_vehicles',
    ];

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
        return $this->{$column} ? asset('storage/' . $this->{$column}) : null;
    }

    public function vehiclePhotoUrls(): array
    {
        return collect($this->photo_vehicles ?? [])
            ->map(fn($p) => asset('storage/' . $p))
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
