<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'phone',
        'role',
        'status',
        'address',
        'id_number',
        'date_of_birth',
        'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
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

    /** null nếu được phép đăng nhập; ngược lại trả về thông báo chặn. */
    public function loginBlockMessage(): ?string
    {
        if ($this->isActive()) {
            return null;
        }

        if ($this->status === 'suspended') {
            return 'Tài khoản của bạn bị tạm ngưng.';
        }

        if ($this->role === 'driver') {
            $profile = $this->relationLoaded('driverProfile')
                ? $this->driverProfile
                : $this->driverProfile()->first();

            if ($profile && $profile->approval_status === 'pending') {
                return 'Hồ sơ tài xế đang chờ duyệt. Vui lòng đợi admin kích hoạt.';
            }

            if ($profile && $profile->approval_status === 'rejected') {
                return 'Hồ sơ tài xế đã bị từ chối.';
            }
        }

        return 'Tài khoản của bạn đã bị vô hiệu hóa.';
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
        return ($this->gender ?? 'male') === 'female' ? 'Nữ' : 'Nam';
    }

    public function avatarInitial(): string
    {
        $name = trim((string) $this->name);

        return $name !== ''
            ? mb_strtoupper(mb_substr($name, 0, 1))
            : '?';
    }
}
