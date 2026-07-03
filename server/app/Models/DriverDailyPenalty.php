<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDailyPenalty extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'penalty_date',
        'consecutive_cancel_count',
        'late_continue_timeout_count',
    ];

    protected function casts(): array
    {
        return [
            'penalty_date' => 'date',
            'consecutive_cancel_count' => 'integer',
            'late_continue_timeout_count' => 'integer',
        ];
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
