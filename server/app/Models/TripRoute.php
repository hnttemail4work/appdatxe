<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripRoute extends Model
{
    protected $table = 'routes';

    protected $fillable = [
        'departure',
        'destination',
        'base_price',
        'distance_km',
        'round_trip_discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price'                  => 'decimal:2',
            'distance_km'                 => 'integer',
            'round_trip_discount_percent' => 'decimal:2',
            'is_active'                   => 'boolean',
        ];
    }

    public function roundTripDiscountPercent(): float
    {
        if ($this->round_trip_discount_percent !== null) {
            return (float) $this->round_trip_discount_percent;
        }

        return \App\Support\PlatformFees::roundTripDiscountPercent();
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'route_id');
    }
}
