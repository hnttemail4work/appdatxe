<?php

namespace App\Models;

use App\Support\DriverWalletConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DriverTripSettlement extends Model
{
    public const DRIVER_TRANSFER_CONFIRMED = 'driver_confirmed';

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

    public function isBlocking(): bool
    {
        return $this->status !== 'completed';
    }

    public function isUnderThreshold(): bool
    {
        return $this->category === 'under_threshold';
    }

    public function driverConfirmedTransfer(): bool
    {
        return $this->transfer_ref === self::DRIVER_TRANSFER_CONFIRMED;
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

    public function settlementCodeIsValid(?string $code): bool
    {
        $code = strtoupper(trim((string) $code));

        return $this->settlement_code !== null
            && strtoupper($this->settlement_code) === $code
            && ($this->settlement_code_expires_at === null || $this->settlement_code_expires_at->isFuture());
    }

    public function settlementCodeExpired(): bool
    {
        return $this->settlement_code_expires_at !== null
            && $this->settlement_code_expires_at->isPast();
    }

    public function categoryLabel(): string
    {
        $threshold = DriverWalletConfig::revenueThresholdShortLabel();

        return match ($this->category) {
            'under_threshold'      => 'Doanh thu chuyến < ' . $threshold,
            'first_over_threshold' => 'Chuyến đầu ≥ ' . $threshold,
            'over_threshold'       => 'Từ chuyến 2 — duy trì ví',
            default                => $this->category,
        };
    }

    /** Ánh xạ trạng thái kết chuyến → bước trên app tài xế. */
    public static function workflowPhaseFromStatus(?string $status): string
    {
        return match ($status) {
            'pending_settle'      => 'needs_settle',
            'pending_driver_code' => 'enter_settle_code',
            'completed'           => 'settled',
            default               => 'needs_settle',
        };
    }

    /** @return Collection<int, Booking> */
    public function scheduleBookings(): Collection
    {
        if ($this->relationLoaded('schedule') && $this->schedule?->relationLoaded('bookings')) {
            return $this->schedule->bookings
                ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true))
                ->values();
        }

        if (! $this->schedule_id) {
            $this->loadMissing('booking');

            return $this->booking ? collect([$this->booking]) : collect();
        }

        return Booking::query()
            ->where('schedule_id', $this->schedule_id)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->orderBy('id')
            ->get();
    }
}
