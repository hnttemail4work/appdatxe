<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWalletTransaction extends Model
{
    protected $fillable = [
        'driver_wallet_id',
        'type',
        'amount',
        'status',
        'transfer_ref',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(DriverWallet::class, 'driver_wallet_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Đã cộng ví',
            'rejected' => 'Từ chối',
            default    => 'Chờ duyệt',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => \App\Support\StatusBadge::SUCCESS,
            'rejected' => \App\Support\StatusBadge::DANGER,
            default    => \App\Support\StatusBadge::PENDING,
        };
    }
}
