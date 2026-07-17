<?php

namespace App\Rules;

use App\Support\AuthIdentifier;
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

        $normalized = AuthIdentifier::normalizePhone((string) $value);

        if ($normalized === '') {
            $fail('Vui lòng nhập số điện thoại hợp lệ.');

            return;
        }

        if (! preg_match('/^0\d{8,10}$/', $normalized)) {
            $fail('Số điện thoại không đúng định dạng Việt Nam.');

            return;
        }

        $existing = AuthIdentifier::findUserByPhone($normalized);

        if ($existing && ($this->ignoreUserId === null || $existing->id !== $this->ignoreUserId)) {
            $fail('Số điện thoại này đã được đăng ký. Vui lòng dùng số khác hoặc đăng nhập bằng số đó.');
        }
    }
}
