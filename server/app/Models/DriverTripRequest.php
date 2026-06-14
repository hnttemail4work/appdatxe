<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTripRequest extends Model
{
    protected $fillable = [
        'schedule_id',
        'customer_id',
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

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id', 'driver_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
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
