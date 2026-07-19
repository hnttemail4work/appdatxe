<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Support\AuthIdentifier;
use App\Support\AuthMessages;
use App\Support\AuthOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthVerificationService
{
    /** @deprecated Dùng AuthOtp::TTL_MINUTES */
    public const REGISTER_TTL_MINUTES = AuthOtp::TTL_MINUTES;

    /** @deprecated Dùng AuthOtp::TTL_MINUTES */
    public const RESET_TTL_MINUTES = AuthOtp::TTL_MINUTES;

    public const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * @return array{code: AuthVerificationCode, plain: string}
     */
    public function issue(
        string $phone,
        string $purpose,
        int $ttlMinutes,
        ?User $user = null,
        array $meta = [],
        string $status = AuthVerificationCode::STATUS_ACTIVE,
    ): array {
        $phone = AuthIdentifier::normalizePhone($phone);
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'Số điện thoại không hợp lệ.',
            ]);
        }

        $plain = $this->randomDigits(6);

        $record = DB::transaction(function () use ($phone, $purpose, $ttlMinutes, $user, $meta, $status, $plain) {
            AuthVerificationCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereIn('status', [AuthVerificationCode::STATUS_ACTIVE, AuthVerificationCode::STATUS_PENDING_ADMIN])
                ->delete();

            return AuthVerificationCode::query()->create([
                'user_id'    => $user?->id,
                'phone'      => $phone,
                'purpose'    => $purpose,
                'code_hash'  => Hash::make($plain),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'attempts'   => 0,
                'status'     => $status,
                'meta'       => array_merge($meta, [
                    'delivery'           => 'admin_fallback',
                    // Admin nhắn tay — chỉ hiện khi mã còn active.
                    'admin_display_code' => $status === AuthVerificationCode::STATUS_ACTIVE ? $plain : null,
                ]),
            ]);
        });

        return ['code' => $record, 'plain' => $plain];
    }

    /**
     * Yêu cầu reset: chờ admin cấp mã (chưa có plain code).
     */
    public function requestPasswordReset(string $phone): AuthVerificationCode
    {
        $phone = AuthIdentifier::normalizePhone($phone);
        $user = AuthIdentifier::findUserByPhone($phone);

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => AuthMessages::PHONE_NOT_FOUND,
            ]);
        }

        AuthVerificationCode::query()
            ->where('phone', $phone)
            ->whereIn('purpose', [
                AuthVerificationCode::PURPOSE_PASSWORD_RESET_REQUEST,
                AuthVerificationCode::PURPOSE_PASSWORD_RESET,
            ])
            ->whereIn('status', [AuthVerificationCode::STATUS_ACTIVE, AuthVerificationCode::STATUS_PENDING_ADMIN])
            ->delete();

        return AuthVerificationCode::query()->create([
            'user_id'    => $user->id,
            'phone'      => $phone,
            'purpose'    => AuthVerificationCode::PURPOSE_PASSWORD_RESET_REQUEST,
            'code_hash'  => Hash::make('pending'),
            'expires_at' => now()->addDay(),
            'attempts'   => 0,
            'status'     => AuthVerificationCode::STATUS_PENDING_ADMIN,
            'meta'       => [
                'role'     => $user->role,
                'delivery' => 'admin_fallback',
            ],
        ]);
    }

    /**
     * Admin cấp mã reset 30 phút từ request đang chờ.
     *
     * @return array{code: AuthVerificationCode, plain: string}
     */
    public function adminIssuePasswordReset(AuthVerificationCode $request): array
    {
        if ($request->purpose !== AuthVerificationCode::PURPOSE_PASSWORD_RESET_REQUEST
            || $request->status !== AuthVerificationCode::STATUS_PENDING_ADMIN) {
            throw ValidationException::withMessages([
                'code' => 'Yêu cầu không còn hiệu lực.',
            ]);
        }

        $user = $request->user;
        $issued = $this->issue(
            $request->phone,
            AuthVerificationCode::PURPOSE_PASSWORD_RESET,
            AuthOtp::TTL_MINUTES,
            $user,
            ['from_request_id' => $request->id, 'role' => $user?->role],
        );

        $request->delete();

        return $issued;
    }

    public function verify(string $phone, string $purpose, string $plain): AuthVerificationCode
    {
        $phone = AuthIdentifier::normalizePhone($phone);
        $plain = trim($plain);

        if (! preg_match('/^\d{6}$/', $plain)) {
            throw ValidationException::withMessages([
                'code' => 'Mã xác minh phải gồm 6 chữ số.',
            ]);
        }

        /** @var AuthVerificationCode|null $record */
        $record = AuthVerificationCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('status', AuthVerificationCode::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();

        if (! $record || ! $record->isUsable()) {
            throw ValidationException::withMessages([
                'code' => 'Mã đã hết hạn hoặc không tồn tại. Vui lòng gửi lại.',
            ]);
        }

        if ((int) $record->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $record->delete();
            throw ValidationException::withMessages([
                'code' => 'Nhập sai quá nhiều lần. Vui lòng gửi lại mã mới.',
            ]);
        }

        if (! Hash::check($plain, $record->code_hash)) {
            $record->increment('attempts');
            throw ValidationException::withMessages([
                'code' => \App\Support\AuthMessages::CODE_INVALID,
            ]);
        }

        // Đã dùng → xóa luôn (không giữ bản consumed).
        $snapshot = $record->replicate();
        $snapshot->id = $record->id;
        $snapshot->status = AuthVerificationCode::STATUS_CONSUMED;
        $snapshot->consumed_at = now();
        $record->delete();

        return $snapshot;
    }

    /**
     * @return array{code: AuthVerificationCode, plain: string}
     */
    public function resend(string $phone, string $purpose, int $ttlMinutes, ?User $user = null): array
    {
        return $this->issue($phone, $purpose, $ttlMinutes, $user);
    }

    /** @return \Illuminate\Support\Collection<int, AuthVerificationCode> */
    public function adminActiveCodes()
    {
        return AuthVerificationCode::query()
            ->with('user')
            ->whereIn('status', [
                AuthVerificationCode::STATUS_ACTIVE,
                AuthVerificationCode::STATUS_PENDING_ADMIN,
            ])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function randomDigits(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }
}
