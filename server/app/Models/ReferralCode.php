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

    /** Số ngày mã từ đặt vé có hiệu lực sau khi kích hoạt. */
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function assignedDriverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'assigned_driver_profile_id');
    }

    public function typeLabel(): string
    {
        if ($this->isDriverInvite()) {
            return 'Tài xế';
        }

        if ($this->type === self::TYPE_BOOKING_TEMP) {
            return 'Khách';
        }

        // Mã hoa hồng admin tạo tay — phân biệt bằng cột Giới thiệu, không gán nhãn Khách/Tài xế.
        return '—';
    }

    /** % hiển thị một cột: QR TX / mã vé = giảm giá KH; mã GT admin = % hoa hồng. */
    public function listPercentLabel(): string
    {
        if ($this->isDriverInvite() || $this->type === self::TYPE_BOOKING_TEMP) {
            return self::formatPercentLabel($this->customerDiscountPercent());
        }

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

    public function isDriverInvite(): bool
    {
        return $this->driver_profile_id !== null;
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
            && ! $this->isSuspended();
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

    /** % hoa hồng trả người giới thiệu — ưu tiên % admin cấu hình trên từng mã. QR tài xế = 0. */
    public function commissionPercent(): float
    {
        if ($this->isDriverInvite()) {
            return max(0.0, (float) ($this->commission_percent ?? 0));
        }

        if ($this->commission_percent !== null) {
            return (float) $this->commission_percent;
        }

        return match ($this->type) {
            self::TYPE_REFERRER => PlatformFees::referralCommissionFirstPercent(),
            default => 0,
        };
    }

    /** % giảm giá vé — mã từ chuyến hoàn tất lấy % admin cấu hình (mặc định 2%). */
    public function customerDiscountPercent(): float
    {
        if ($this->type === self::TYPE_BOOKING_TEMP) {
            return PlatformFees::bookingQrDiscountPercent();
        }

        if ($this->isDriverInvite()) {
            if ($this->customer_discount_percent !== null) {
                return max(0.0, (float) $this->customer_discount_percent);
            }

            return PlatformFees::driverInviteQrDiscountPercent();
        }

        if ($this->customer_discount_percent !== null) {
            return max(0.0, (float) $this->customer_discount_percent);
        }

        return 0.0;
    }

    /** Nhãn nguồn giảm giá hiển thị cho khách. */
    public function customerDiscountSourceLabel(): string
    {
        return match (true) {
            $this->isDriverInvite() => 'QR tài xế',
            $this->type === self::TYPE_BOOKING_TEMP => 'giới thiệu',
            $this->type === self::TYPE_REFERRER => 'mã GT',
            default => 'giới thiệu',
        };
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
        if ($this->type === self::TYPE_REFERRER) {
            return '—';
        }

        if ($this->status === self::STATUS_PENDING) {
            return 'Chờ hoàn tất';
        }

        if (! $this->expires_at) {
            return '—';
        }

        if ($this->isExpired()) {
            return 'Hết hạn ' . $this->expires_at->format('d/m/Y');
        }

        return $this->expires_at->format('d/m/Y H:i');
    }

    public function expiryColor(): string
    {
        if ($this->type === self::TYPE_REFERRER) {
            return 'neutral';
        }

        if ($this->status === self::STATUS_PENDING) {
            return 'pending';
        }

        return $this->isExpired() ? 'danger' : 'info';
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
