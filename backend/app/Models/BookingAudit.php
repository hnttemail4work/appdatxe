<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAudit extends Model
{
    protected $fillable = [
        'booking_id',
        'actor_id',
        'action',
        'before_state',
        'after_state',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
