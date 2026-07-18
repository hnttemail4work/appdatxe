<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthVerificationCode extends Model
{
    public const PURPOSE_REGISTER_OTP = 'register_otp';

    public const PURPOSE_PASSWORD_RESET_REQUEST = 'password_reset_request';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PENDING_ADMIN = 'pending_admin';

    protected $fillable = [
        'user_id',
        'phone',
        'purpose',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
            'meta'        => 'array',
            'attempts'    => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->consumed_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
