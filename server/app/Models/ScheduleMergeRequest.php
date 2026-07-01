<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleMergeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'target_schedule_id',
        'source_schedule_id',
        'driver_id',
        'requested_by',
        'status',
        'expires_at',
        'responded_at',
        'driver_note',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function targetSchedule()
    {
        return $this->belongsTo(Schedule::class, 'target_schedule_id');
    }

    public function sourceSchedule()
    {
        return $this->belongsTo(Schedule::class, 'source_schedule_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Chờ tài xế xác nhận',
            self::STATUS_ACCEPTED  => 'Tài xế đã đồng ý gom',
            self::STATUS_REJECTED  => 'Tài xế từ chối gom',
            self::STATUS_EXPIRED   => 'Hết hạn chờ xác nhận',
            self::STATUS_CANCELLED => 'Đã hủy',
            default                => $this->status,
        };
    }
}
