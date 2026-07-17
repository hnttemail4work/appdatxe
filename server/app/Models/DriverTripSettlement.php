<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTripSettlement extends Model
{
    protected $fillable = [
        'driver_wallet_id',
        'schedule_id',
        'booking_id',
        'revenue_amount',
        'platform_fee_amount',
        'category',
        'status',
        'transfer_ref',
        'settlement_code',
        'settlement_code_expires_at',
        'operator_code_issued_at',
        'operator_code_issued_by',
        'driver_settled_at',
        'admin_confirmed_at',
        'operator_approved_at',
        'admin_confirmed_by',
        'operator_approved_by',
    ];

    protected function casts(): array
    {
        return [
            'revenue_amount'       => 'integer',
            'platform_fee_amount'  => 'integer',
            'driver_settled_at'          => 'datetime',
            'settlement_code_expires_at' => 'datetime',
            'operator_code_issued_at'    => 'datetime',
            'admin_confirmed_at'         => 'datetime',
            'operator_approved_at' => 'datetime',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(DriverWallet::class, 'driver_wallet_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_settle'      => 'Chờ cấp mã kết chuyến',
            'pending_driver_code' => 'Chờ tài xế nhập mã',
            'completed'           => 'Hoàn thành chuyến',
            default               => $this->status,
        };
    }

    /** Ánh xạ trạng thái kết chuyến → bước trên app tài xế. */
}
