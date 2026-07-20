<?php

namespace App\Models;

use App\Support\AuthOtp;
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

    /** @deprecated Dùng AuthOtp::TTL_MINUTES */
    public const PENDING_APPROVAL_TTL_HOURS = 0;

    /** Thời gian chờ duyệt CCCD — khớp TTL OTP chung. */
    public const PENDING_APPROVAL_WAIT_MINUTES = AuthOtp::TTL_MINUTES;

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'login_fail_count',
        'login_locked_until',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'role',
        'status',
        'approval_status',
        'register_otp_verified_at',
        'rejection_reason',
        'rejection_reason_at',
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
            'rejection_reason_at'       => 'datetime',
            'register_otp_verified_at'  => 'datetime',
        ];
    }

    public function hasRejectionNote(): bool
    {
        return filled($this->rejection_reason);
    }

    public function customerDisplayStatusLabel(): string
    {
        return match (true) {
            $this->approval_status === self::APPROVAL_PENDING => 'Chờ duyệt',
            $this->approval_status === self::APPROVAL_REJECTED => 'Từ chối',
            default => \App\Support\AdminAccountStatus::label($this->status, 'customer'),
        };
    }

    public function customerDisplayStatusColor(): string
    {
        return match (true) {
            $this->approval_status === self::APPROVAL_PENDING => \App\Support\StatusBadge::PENDING,
            $this->approval_status === self::APPROVAL_REJECTED => \App\Support\StatusBadge::DANGER,
            default => \App\Support\AdminAccountStatus::color($this->status),
        };
    }

    public function isAccountRunning(): bool
    {
        return \App\Support\AdminAccountStatus::isRunning($this->status);
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

    public function isCustomerApprovalRejected(): bool
    {
        return $this->isCustomer() && $this->approval_status === self::APPROVAL_REJECTED;
    }

    /** Thời điểm gửi yêu cầu duyệt — lúc tạo hồ sơ (OTP cấp sau khi admin duyệt). */
    public function customerApprovalRequestedAt(): ?\Carbon\CarbonInterface
    {
        if (! $this->isCustomer()) {
            return null;
        }

        return $this->created_at;
    }

    /** Đã duyệt nhưng chưa xác minh OTP lần đầu → bắt buộc nhập OTP (admin gửi từ tab OTP). */
    public function needsPostApprovalRegisterOtp(): bool
    {
        if ($this->register_otp_verified_at) {
            return false;
        }

        if ($this->isCustomer()) {
            return $this->approval_status === self::APPROVAL_APPROVED;
        }

        if ($this->role === 'driver') {
            $profile = $this->relationLoaded('driverProfile')
                ? $this->driverProfile
                : $this->driverProfile()->first();

            return $profile !== null && $profile->approval_status === 'approved';
        }

        return false;
    }

    /** Đang chờ duyệt (chưa hết hạn / chưa từ chối) — giữ trang OTP, chưa nhập mã. */
    public function isAwaitingApprovalForRegisterOtp(): bool
    {
        if ($this->register_otp_verified_at) {
            return false;
        }

        if ($this->isCustomer()) {
            return $this->isCustomerApprovalPending() && ! $this->isCustomerPendingApprovalExpired();
        }

        if ($this->role === 'driver') {
            $profile = $this->relationLoaded('driverProfile')
                ? $this->driverProfile
                : $this->driverProfile()->first();

            return $profile !== null
                && $profile->isPendingApproval()
                && ! $profile->isPendingApprovalExpired();
        }

        return false;
    }

    /** Được giữ trên /dang-ky/otp (chờ duyệt hoặc đã duyệt chờ nhập OTP). */
    public function canStayOnRegisterOtpPage(): bool
    {
        return $this->isAwaitingApprovalForRegisterOtp() || $this->needsPostApprovalRegisterOtp();
    }

    public function customerApprovalDeadlineAt(): ?\Carbon\CarbonInterface
    {
        $requestedAt = $this->customerApprovalRequestedAt();
        if (! $requestedAt) {
            return null;
        }

        return $requestedAt->copy()->addMinutes(AuthOtp::TTL_MINUTES);
    }

    /** Chờ duyệt quá hạn mà admin chưa duyệt / chưa phản hồi. */
    public function isCustomerPendingApprovalExpired(): bool
    {
        if (! $this->isCustomerApprovalPending()) {
            return false;
        }

        $deadline = $this->customerApprovalDeadlineAt();

        return $deadline !== null && $deadline->isPast();
    }

    /**
     * Chỉ hồ sơ «Đã từ chối» mới mở SĐT đăng ký lại.
     * Hết hạn chờ duyệt → đánh dấu từ chối trước (nếu đã lưu DB).
     */
    public function customerAllowsFreshRegistration(): bool
    {
        if (! $this->isCustomer()) {
            return false;
        }

        if ($this->isCustomerApprovalRejected()) {
            return true;
        }

        if (! ($this->isCustomerApprovalPending() && $this->isCustomerPendingApprovalExpired())) {
            return false;
        }

        if (! $this->exists) {
            return true;
        }

        app(\App\Services\PendingApprovalExpiryService::class)->expireCustomer($this);
        $this->refresh();

        return $this->isCustomerApprovalRejected();
    }

    /** Khách đã duyệt CCCD (và active) — được đặt xe. */
    public function canBookTrips(): bool
    {
        return $this->isCustomer()
            && $this->isActive()
            && $this->approval_status === self::APPROVAL_APPROVED;
    }

    /** Banner / flash khi khách vừa OTP xong hoặc đang chờ duyệt. */
    public function pendingApprovalNotice(): ?string
    {
        if (! $this->isCustomerApprovalPending()) {
            return null;
        }

        return AuthOtp::pendingApprovalNotice(isCustomer: true);
    }

    public function bookingBlockMessage(): ?string
    {
        if (! $this->isCustomer()) {
            return null;
        }

        if ($this->canBookTrips()) {
            return null;
        }

        if ($notice = $this->pendingApprovalNotice()) {
            return $notice;
        }

        if ($this->approval_status === self::APPROVAL_REJECTED) {
            return 'Hồ sơ khách hàng đã bị từ chối. Vui lòng liên hệ hỗ trợ.';
        }

        if (! \App\Support\AdminAccountStatus::isRunning($this->status)) {
            return 'Tài khoản đang bị khóa.';
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
            return 'Tài khoản đang bị khóa.';
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

            return 'Tài khoản đang bị khóa.';
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

            return 'Tài khoản đang bị khóa.';
        }

        if ($this->isActive()) {
            return null;
        }

        return 'Tài khoản đang bị khóa.';
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
