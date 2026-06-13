<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatReservation extends Model
{
    protected $fillable = [
        'schedule_id',
        'booking_id',
        'customer_id',
        'seat_number',
        'reservation_token',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
