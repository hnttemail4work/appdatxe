<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'customer_id',
        'schedule_id',
        'seat_numbers',
        'ticket_code',
        'booking_reference',
        'total_price',
        'payment_status',
        'trip_status',
        'booking_status',
        'pickup_address',
        'dropoff_address',
        'notes',
        'hold_expires_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_numbers'   => 'array',
            'total_price'    => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'confirmed_at'   => 'datetime',
            'completed_at'   => 'datetime',
            'cancelled_at'   => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function hasPendingPaymentClaim(): bool
    {
        return $this->paymentTransactions()->where('status', 'pending')->exists();
    }

    public function isConfirmedForDriver(): bool
    {
        return $this->booking_status === 'confirmed'
            && $this->payment_status === 'paid'
            && $this->trip_status === 'confirmed';
    }

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function audits()
    {
        return $this->hasMany(BookingAudit::class);
    }
}
