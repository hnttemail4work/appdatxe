<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripReview extends Model
{
    public const SENTIMENT_LIKE = 'like';

    public const SENTIMENT_DISLIKE = 'dislike';

    protected $fillable = [
        'booking_id',
        'schedule_id',
        'driver_id',
        'driver_profile_id',
        'sentiment',
        'comment',
        'contact_phone',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function sentimentIcon(): string
    {
        return $this->sentiment === self::SENTIMENT_LIKE ? '👍' : '👎';
    }

    /** Nhãn đồng bộ với tab tài xế (Thích / Không thích). */
    public function driverPreferenceLabel(): string
    {
        return $this->sentiment === self::SENTIMENT_LIKE ? 'Thích' : 'Không thích';
    }
}
