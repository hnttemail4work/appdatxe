<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Models\DriverProfile;
use App\Models\User;
use App\Support\AuthAudience;
use App\Support\AuthIdentifier;
use App\Support\AuthOtp;
use App\Support\AuthPhone;
use App\Support\DriverFieldRules;
use App\Support\PinPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly DriverPhotoService $photos,
        private readonly CustomerDocumentService $customerDocuments,
        private readonly AuthVerificationService $verification,
    ) {
    }

    /** Khách/tài xế đã duyệt nhưng chưa xác minh OTP lần đầu. */
    public function shouldResumeRegisterOtp(User $user): bool
    {
        return $user->needsPostApprovalRegisterOtp();
    }

    /**
     * Xóa slot đăng ký cũ (đã từ chối) để SĐT đăng ký lại.
     * Hết hạn chờ duyệt → đánh dấu từ chối trước (giữ record cho admin), rồi mới xóa khi đăng ký lại.
     */
    public function releaseCustomerRegistrationIfReusable(User $user): bool
    {
        $expiry = app(PendingApprovalExpiryService::class);

        if ($user->isCustomerApprovalPending() && $user->isCustomerPendingApprovalExpired()) {
            $expiry->expireCustomer($user);
            $user->refresh();
        }

        return $expiry->deleteCustomerRegistration($user);
    }

    /** Giữ trang OTP (chờ duyệt hoặc nhập mã) — không tự cấp OTP. */
    public function openRegisterOtpPage(Request $request, User $user): string
    {
        $request->session()->put('pending_register_otp.user_id', $user->id);
        AuthAudience::rememberFromUser($request, $user);

        return route('auth.register.otp');
    }

    /** Gắn session OTP; chỉ cấp mã mới nếu chưa có mã active (giữ mã admin đang xem ở tab OTP). */
    public function beginRegisterOtpResume(Request $request, User $user): string
    {
        $active = AuthVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', AuthVerificationCode::PURPOSE_REGISTER_OTP)
            ->where('status', AuthVerificationCode::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();

        if (! $active || ! $active->isUsable()) {
            $this->verification->resend(
                (string) $user->phone,
                AuthVerificationCode::PURPOSE_REGISTER_OTP,
                AuthOtp::TTL_MINUTES,
                $user,
            );
        }

        return $this->openRegisterOtpPage($request, $user);
    }

    /** @deprecated Dùng beginRegisterOtpResume */
    public function beginCustomerOtpResume(Request $request, User $user): string
    {
        return $this->beginRegisterOtpResume($request, $user);
    }

    /** @deprecated Dùng shouldResumeRegisterOtp */
    public function customerShouldResumeOtp(User $user): bool
    {
        return $this->shouldResumeRegisterOtp($user);
    }

    public function hasCompletedRegisterOtp(User $user): bool
    {
        return $user->register_otp_verified_at !== null;
    }

    /**
     * Cấp OTP đăng ký sau khi admin duyệt — hiện ở tab OTP / Reset.
     *
     * @return array{code: AuthVerificationCode, plain: string}
     */
    public function issueRegisterOtpAfterApproval(User $user): array
    {
        return $this->verification->issue(
            (string) $user->phone,
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            AuthOtp::TTL_MINUTES,
            $user,
            ['role' => $user->role, 'after_approval' => true],
        );
    }

    /** @return array<string, mixed> */
    public function driverRules(): array
    {
        return DriverFieldRules::registrationRules();
    }

    /** @return array<string, mixed> */
    public function customerRules(): array
    {
        return array_merge(
            [
                'phone'                 => AuthPhone::rules(unique: true),
                'password'              => PinPassword::rules(confirmed: true),
                'password_confirmation' => ['required', 'string', 'digits:'.PinPassword::LENGTH],
                'terms'                 => ['accepted'],
            ],
            DriverFieldRules::idCardPhotoRules(true),
        );
    }

    /**
     * @return array{user: User}
     */
    public function registerCustomer(array $validated, Request $request): array
    {
        return DB::transaction(function () use ($validated, $request): array {
            $phone = AuthIdentifier::normalizePhone((string) $validated['phone']);

            $existing = AuthIdentifier::findUserByPhone($phone);
            if ($existing) {
                $this->releaseCustomerRegistrationIfReusable($existing);
            }

            $pin = PinPassword::assertValid($validated['password'] ?? null);

            $user = User::query()->create([
                'name'                 => $phone,
                'email'                => null,
                'password'             => $pin,
                'must_change_password' => false,
                'phone'                => $phone,
                'gender'               => null,
                'date_of_birth'        => null,
                'id_number'            => null,
                'address'              => null,
                'role'                 => 'customer',
                'status'               => 'inactive',
                'approval_status'      => User::APPROVAL_PENDING,
            ]);

            $this->customerDocuments->storeRegistrationPhotos($user, $request);

            app(CustomerAccountService::class)->linkExistingBookings($user);

            return ['user' => $user];
        });
    }

    /**
     * @return array{user: User}
     */
    public function registerDriver(array $validated, Request $request): array
    {
        return DB::transaction(function () use ($validated, $request): array {
            $email = filled($validated['email'] ?? null)
                ? trim((string) $validated['email'])
                : null;

            $phone = AuthIdentifier::normalizePhone((string) $validated['phone']);
            $name = trim((string) ($validated['name'] ?? ''));
            $pin = PinPassword::assertValid($validated['password'] ?? null);

            $user = User::query()->create([
                'name'                 => $name !== '' ? $name : $phone,
                'email'                => $email,
                'password'             => $pin,
                'must_change_password' => false,
                'phone'                => $phone,
                'id_number'            => $validated['id_number'] ?? null,
                'date_of_birth'        => $validated['date_of_birth'] ?? null,
                'address'              => $validated['address'] ?? null,
                'role'                 => 'driver',
                'status'               => 'inactive',
            ]);

            $licenseNumber = $this->resolveLicenseNumber(preg_replace('/\D/', '', $validated['phone']));
            $licenseClass = 'B2';
            $licenseExpiry = now()->addYears(10)->toDateString();

            $soundDefaults = \App\Support\NotificationSoundSettings::forClient();

            $profile = DriverProfile::query()->create([
                'user_id'               => $user->id,
                'operator_id'           => null,
                'approval_status'       => 'pending',
                'license_number'        => $licenseNumber,
                'license_class'         => $licenseClass,
                'license_expiry'        => $licenseExpiry,
                'experience_years'      => (int) ($validated['experience_years'] ?? 0),
                'status'                => 'inactive',
                'availability_status'   => 'available',
                'notes'                 => null,
                'bank_name'             => $validated['bank_name'] ?? null,
                'bank_account'          => $validated['bank_account'] ?? null,
                'vehicle_license_plate' => $validated['vehicle_license_plate'],
                'vehicle_type'          => $validated['vehicle_type'],
                'vehicle_brand'         => null,
                'vehicle_model'         => null,
                'vehicle_color'         => null,
                'vehicle_seats'         => \App\Support\DriverVehicleOptions::seatsFor($validated['vehicle_type']),
                'sound_enabled'         => (bool) ($soundDefaults['enabled'] ?? true),
                'sound_preset'          => \App\Support\DriverSoundPresets::normalize($soundDefaults['preset'] ?? null),
            ]);

            $this->photos->storeRegistrationPhotos($profile, $request);

            return ['user' => $user];
        });
    }

    private function resolveLicenseNumber(string $phoneDigits): string
    {
        $root = 'TX-' . ($phoneDigits !== '' ? $phoneDigits : Str::upper(Str::random(6)));
        $candidate = $root;
        $suffix = 0;

        while (DriverProfile::query()->where('license_number', $candidate)->exists()) {
            $suffix++;
            $candidate = $root . '-' . $suffix;
        }

        return $candidate;
    }
}
