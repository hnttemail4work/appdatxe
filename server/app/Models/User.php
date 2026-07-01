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
        'phone',
        'role',
        'status',
        'address',
        'id_number',
        'date_of_birth',
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
            'password' => 'hashed',
        ];
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class, 'user_id');
    }

    public function managedDrivers()
    {
        return $this->hasMany(DriverProfile::class, 'operator_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'operator_id');
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

    public function bookingAudits()
    {
        return $this->hasMany(BookingAudit::class, 'actor_id');
    }
}
