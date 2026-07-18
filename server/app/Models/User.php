<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'login_fail_count',
        'login_locked_until',
        'phone',
        'role',
        'status',
        'approval_status',
        'address',
        'id_number',
        'date_of_birth',
        'gender',
        'photo_id_card',
        'photo_id_card_back',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'date_of_birth'        => 'date',
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
            'login_fail_count'     => 'integer',
            'login_locked_until'   => 'datetime',
        ];
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    public function customerProfileChangeRequests(): HasMany
    {
        return $this->hasMany(CustomerProfileChangeRequest::class, 'user_id');
    }

    public function idCardPhotoUrl(string $field): ?string
    {
        $path = $this->{$field} ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function isCustomerApprovalPending(): bool
    {
        return $this->isCustomer() && $this->approval_status === self::APPROVAL_PENDING;
    }

    /** Khách đã duyệt CCCD (và active) — được đặt xe. */
    public function canBookTrips(): bool
    {
        return $this->isCustomer()
            && $this->isActive()
            && $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function bookingBlockMessage(): ?string
    {
        if (! $this->isCustomer()) {
            return null;
        }

        if ($this->canBookTrips()) {
            return null;
        }

        if ($this->approval_status === self::APPROVAL_PENDING || $this->status === 'inactive') {
            return 'Hồ sơ đang chờ admin duyệt CCCD. Bạn có thể xem trang chủ nhưng chưa đặt được xe.';
        }

        if ($this->approval_status === self::APPROVAL_REJECTED) {
            return 'Hồ sơ khách hàng đã bị từ chối. Vui lòng liên hệ hỗ trợ.';
        }

        if ($this->status === 'suspended') {
            return 'Tài khoản của bạn bị tạm ngưng.';
        }

        return 'Tài khoản chưa được phép đặt xe.';
    }

    public function merchantProfile()
    {
        return $this->hasOne(MerchantProfile::class, 'user_id');
    }

    /** Email hiển thị trên form — ẩn placeholder hệ thống @noemail.local */
    public function emailForForm(): string
    {
        $email = trim((string) ($this->email ?? ''));

        if ($email === '' || str_ends_with(strtolower($email), '@noemail.local')) {
            return '';
        }

        return $email;
    }

    public function approvedMerchantProfiles()
    {
        return $this->hasMany(MerchantProfile::class, 'approved_by');
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'operator_id');
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * null nếu được phép đăng nhập phiên (kể cả pending duyệt hồ sơ).
     * Đặt xe / nhận chuyến vẫn bị chặn riêng khi chưa approved.
     */
    public function loginBlockMessage(): ?string
    {
        if ($this->status === 'suspended') {
            return 'Tài khoản của bạn bị tạm ngưng.';
        }

        if ($this->role === 'driver') {
            $profile = $this->relationLoaded('driverProfile')
                ? $this->driverProfile
                : $this->driverProfile()->first();

            if ($profile && $profile->approval_status === 'rejected') {
                return 'Hồ sơ tài xế đã bị từ chối.';
            }

            // Chờ duyệt: cho vào app (chặn nhận chuyến ở tầng nghiệp vụ).
            if ($profile && $profile->approval_status === 'pending') {
                return null;
            }

            if ($this->isActive()) {
                return null;
            }

            return 'Tài khoản của bạn đã bị vô hiệu hóa.';
        }

        if ($this->isCustomer()) {
            if ($this->approval_status === self::APPROVAL_REJECTED) {
                return 'Hồ sơ khách hàng đã bị từ chối.';
            }

            // Chờ duyệt CCCD: cho xem home, chưa đặt xe.
            if ($this->approval_status === self::APPROVAL_PENDING) {
                return null;
            }

            if ($this->isActive() && $this->approval_status === self::APPROVAL_APPROVED) {
                return null;
            }

            return 'Tài khoản của bạn đã bị vô hiệu hóa.';
        }

        if ($this->isActive()) {
            return null;
        }

        return 'Tài khoản của bạn đã bị vô hiệu hóa.';
    }

    /** Nhãn hiển thị: ưu tiên họ tên đã cập nhật, không thì số điện thoại. */
    public function preferredDisplayName(): string
    {
        $phone = trim((string) ($this->phone ?? ''));
        $name = trim((string) ($this->name ?? ''));

        if ($name === '') {
            return $phone !== '' ? $phone : 'Tài xế';
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone) ?: '';
        $nameDigits = preg_replace('/\D+/', '', $name) ?: '';
        $nameLooksLikePhone = $phoneDigits !== '' && $nameDigits === $phoneDigits;
        $nameIsMostlyDigits = (bool) preg_match('/^[\d\s.+()-]+$/', $name);

        if (! $nameLooksLikePhone && ! $nameIsMostlyDigits) {
            return $name;
        }

        return $phone !== '' ? $phone : $name;
    }

    public function age(): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    public function genderLabel(): string
    {
        if ($this->gender === null || $this->gender === '') {
            return 'Chưa cập nhật';
        }

        return $this->gender === 'female' ? 'Nữ' : 'Nam';
    }

    public function avatarInitial(): string
    {
        $name = trim((string) $this->name);

        return $name !== ''
            ? mb_strtoupper(mb_substr($name, 0, 1))
            : '?';
    }
}
