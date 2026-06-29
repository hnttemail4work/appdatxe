<?php

namespace App\Models;

use App\Support\PlatformFees;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCode extends Model
{
    public const TYPE_REFERRER = 'referrer';

    public const TYPE_BOOKING_TEMP = 'booking_temp';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'code',
        'type',
        'name',
        'phone',
        'booking_id',
        'status',
        'created_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReferralCode $referralCode): void {
            if (empty($referralCode->code)) {
                $referralCode->code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'GT' . strtoupper(substr(md5(uniqid('gt', true)), 0, 6));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REFERRER => 'Người giới thiệu',
            self::TYPE_BOOKING_TEMP => 'Từ đặt vé',
            default => $this->type,
        };
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_SUSPENDED) {
            return 'Tạm ngưng';
        }

        if ($this->status === self::STATUS_ACTIVE) {
            return 'Sử dụng';
        }

        return 'Đang chờ';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_SUSPENDED => 'neutral',
            default => 'pending',
        };
    }

    /** % hoa hồng cho người giới thiệu (mã admin tạo) — khách đặt qua QR không được giảm giá. */
    public function commissionPercent(): float
    {
        return match ($this->type) {
            self::TYPE_REFERRER => PlatformFees::referralCommissionFirstPercent(),
            self::TYPE_BOOKING_TEMP => PlatformFees::referralCommissionRepeatPercent(),
            default => 0,
        };
    }

    public function commissionTierLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REFERRER => 'Lần 1',
            self::TYPE_BOOKING_TEMP => 'Lần 2',
            default => '—',
        };
    }

    /** % giảm giá vé — chỉ mã phát sinh từ khách đặt chuyến thành công. */
    public function customerDiscountPercent(): float
    {
        if ($this->type !== self::TYPE_BOOKING_TEMP) {
            return 0.0;
        }

        return PlatformFees::referralCommissionRepeatPercent();
    }

    public function grantsCustomerDiscount(): bool
    {
        return $this->type === self::TYPE_BOOKING_TEMP;
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function landingUrl(): string
    {
        return route('home', ['ref' => $this->code]);
    }
}
