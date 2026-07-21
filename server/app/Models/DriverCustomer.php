<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverCustomer extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'contact_phone',
        'phone_key',
        'passenger_name',
        'referral_code_id',
        'first_booking_id',
        'last_booking_id',
        'bookings_count',
        'last_booked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_booked_at' => 'datetime',
            'bookings_count' => 'integer',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function firstBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'first_booking_id');
    }

    public function lastBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'last_booking_id');
    }
}
