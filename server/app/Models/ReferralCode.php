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

    /** Legacy booking-temp validity retained for historical migrations. */
    public const BOOKING_CODE_VALIDITY_DAYS = 90;

    protected $fillable = [
        'code',
        'type',
        'name',
        'phone',
        'booking_id',
        'driver_profile_id',
        'assigned_driver_profile_id',
        'status',
        'commission_percent',
        'customer_discount_percent',
        'created_by',
        'activated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at'   => 'datetime',
            'commission_percent' => 'float',
            'customer_discount_percent' => 'float',
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

    public function assignedDriverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'assigned_driver_profile_id');
    }

    public function typeLabel(): string
    {
        return '—';
    }

    /** % hoa hồng hiển thị cho mã người giới thiệu. */
    public function listPercentLabel(): string
    {
        if ($this->type === self::TYPE_REFERRER) {
            return self::formatPercentLabel($this->commissionPercent());
        }

        return '—';
    }

    public static function formatPercentLabel(float $percent): string
    {
        if (abs($percent - round($percent)) < 0.05) {
            return (string) (int) round($percent).'%';
        }

        return rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';
    }

    /** Mã hoa hồng admin tạo và đã gán cho tài xế (không phải QR giảm giá mời bạn). */
    public function isAssignedCommissionCode(): bool
    {
        return $this->type === self::TYPE_REFERRER
            && $this->driver_profile_id === null
            && $this->assigned_driver_profile_id !== null;
    }

    public function canAssignToDriver(): bool
    {
        return $this->type === self::TYPE_REFERRER
            && $this->driver_profile_id === null
            && ! $this->isSuspended()
            && (float) $this->commissionPercent() <= 0;
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

    /** % hoa hồng trả người giới thiệu — ưu tiên % admin cấu hình trên từng mã. */
    public function commissionPercent(): float
    {
        if ($this->commission_percent !== null) {
            return (float) $this->commission_percent;
        }

        return match ($this->type) {
            self::TYPE_REFERRER => PlatformFees::referralCommissionFirstPercent(),
            default => 0,
        };
    }

    /** % giảm giá khách được cấu hình trực tiếp trên mã. */
    public function customerDiscountPercent(): float
    {
        if ($this->customer_discount_percent !== null) {
            return max(0.0, (float) $this->customer_discount_percent);
        }

        return 0.0;
    }

    /** Nhãn nguồn giảm giá hiển thị cho khách. */
    public function customerDiscountSourceLabel(): string
    {
        return $this->type === self::TYPE_REFERRER ? 'mã GT' : 'giới thiệu';
    }

    public function grantsCustomerDiscount(): bool
    {
        return $this->customerDiscountPercent() > 0;
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Nhãn cột hết hạn trên admin. */
    public function expiryLabel(): string
    {
        return '—';
    }

    public function expiryColor(): string
    {
        return 'neutral';
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
