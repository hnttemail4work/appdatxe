<?php

namespace App\Rules;

use App\Models\DriverProfile;
use App\Services\PendingApprovalExpiryService;
use App\Support\AuthIdentifier;
use App\Support\AuthMessages;
use App\Support\AuthPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueNormalizedPhone implements ValidationRule
{
    public function __construct(private ?int $ignoreUserId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        if (! AuthPhone::isValid((string) $value)) {
            $fail(AuthMessages::PHONE_INVALID);

            return;
        }

        $normalized = AuthPhone::normalize((string) $value);
        $existing = AuthIdentifier::findUserByPhone($normalized);

        if (! $existing) {
            return;
        }

        if ($this->ignoreUserId !== null && $existing->id === $this->ignoreUserId) {
            return;
        }

        // Chỉ hồ sơ ở tab «Đã từ chối» mới cho đăng ký lại (KH + TX).
        if ($this->allowsFreshRegistration($existing)) {
            return;
        }

        $fail(AuthMessages::PHONE_TAKEN);
    }

    private function allowsFreshRegistration(\App\Models\User $user): bool
    {
        $expiry = app(PendingApprovalExpiryService::class);

        if ($user->isCustomer()) {
            if ($user->isCustomerApprovalPending() && $user->isCustomerPendingApprovalExpired()) {
                $expiry->expireCustomer($user);
                $user->refresh();
            }

            return $user->isCustomerApprovalRejected();
        }

        if ($user->role === 'driver') {
            $profile = $user->relationLoaded('driverProfile')
                ? $user->driverProfile
                : DriverProfile::query()->where('user_id', $user->id)->first();

            if (! $profile) {
                return true;
            }

            if ($profile->isPendingApproval() && $profile->isPendingApprovalExpired()) {
                $expiry->expireDriver($profile);
                $profile->refresh();
            }

            return $profile->isRejected();
        }

        return false;
    }
}
