<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'operator_id',
        'license_plate',
        'type',
        'capacity',
        'photo_path',
        'status',
    ];

    public function photoUrl(): ?string
    {
        return \App\Services\VehiclePhotoService::publicUrl($this->photo_path);
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
