<?php

namespace App\Rules;

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

        if ($existing && ($this->ignoreUserId === null || $existing->id !== $this->ignoreUserId)) {
            $fail(AuthMessages::PHONE_TAKEN);
        }
    }
}
