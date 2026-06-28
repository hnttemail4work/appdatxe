<?php

namespace App\Models;

use App\Support\DriverWalletConfig;
use Illuminate\Database\Eloquent\Model;

class DriverWallet extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'balance',
        'cumulative_revenue',
        'completed_settlements_count',
        'wallet_gate_enabled',
        'platform_fee_deadline_at',
        'accept_trips_blocked_at',
        'accept_trips_block_reason',
    ];

    protected function casts(): array
    {
        return [
            'balance'                      => 'integer',
            'cumulative_revenue'           => 'integer',
            'completed_settlements_count'  => 'integer',
            'wallet_gate_enabled'          => 'boolean',
            'platform_fee_deadline_at'     => 'datetime',
            'accept_trips_blocked_at'      => 'datetime',
        ];
    }

    public function driverProfile()
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function settlements()
    {
        return $this->hasMany(DriverTripSettlement::class);
    }

    public function transactions()
    {
        return $this->hasMany(DriverWalletTransaction::class);
    }

    public function hasMinBalance(): bool
    {
        return $this->balance > DriverWalletConfig::MIN_BALANCE;
    }

    public function pendingSettlements()
    {
        return $this->settlements()->whereNot('status', 'completed');
    }

    public function isAcceptTripsBlocked(): bool
    {
        return $this->accept_trips_blocked_at !== null;
    }
}
