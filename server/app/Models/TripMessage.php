<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripMessage extends Model
{
    protected $fillable = [
        'booking_id',
        'sender_user_id',
        'sender_role',
        'body',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
