<?php

namespace App\Models;

use App\Services\DriverCancelRateService;
use Illuminate\Database\Eloquent\Model;

class DriverTripRequest extends Model
{
    protected $fillable = [
        'schedule_id',
        'contact_phone',
        'driver_id',
        'status',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (DriverTripRequest $request): void {
            if ($request->driver_id) {
                app(DriverCancelRateService::class)->recordOfferForUserId((int) $request->driver_id);
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id', 'driver_id');
    }

    public function contactPhone(): ?string
    {
        return $this->contact_phone;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function acceptTimeRemainingLabel(): ?string
    {
        if (! $this->expires_at || ! $this->expires_at->isFuture()) {
            return null;
        }

        $seconds = $this->expires_at->getTimestamp() - now()->getTimestamp();
        if ($seconds <= 0) {
            return null;
        }

        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes . ' phút';
    }

    public function relatedBooking(): ?Booking
    {
        $this->loadMissing('schedule.bookings');

        $active = $this->schedule->bookings
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true));

        return $active->first(fn (Booking $b): bool => $b->matchesContactPhone((string) $this->contact_phone));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'accepted'  => 'Tài xế đã nhận',
            'rejected'  => 'Tài xế từ chối',
            'expired'   => 'Hết thời gian chờ',
            'cancelled' => 'Đã hủy',
            default     => 'Đang chờ tài xế',
        };
    }
}
