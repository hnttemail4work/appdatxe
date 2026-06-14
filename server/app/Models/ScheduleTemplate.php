<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'driver_name',
        'departure_time',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    public function route()
    {
        return $this->belongsTo(TripRoute::class, 'route_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'template_id');
    }
}
