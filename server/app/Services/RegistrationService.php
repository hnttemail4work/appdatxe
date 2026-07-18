<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Models\DriverProfile;
use App\Models\User;
use App\Support\AuthIdentifier;
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
     * @return array{user: User, otp_plain: string}
     */
    public function registerCustomer(array $validated, Request $request): array
    {
        return DB::transaction(function () use ($validated, $request): array {
            $phone = AuthIdentifier::normalizePhone((string) $validated['phone']);
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

            $issued = $this->verification->issue(
                $phone,
                AuthVerificationCode::PURPOSE_REGISTER_OTP,
                AuthVerificationService::REGISTER_TTL_MINUTES,
                $user,
                ['role' => 'customer'],
            );

            return ['user' => $user, 'otp_plain' => $issued['plain']];
        });
    }

    /**
     * @return array{user: User, otp_plain: string}
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
            ]);

            $this->photos->storeRegistrationPhotos($profile, $request);

            $issued = $this->verification->issue(
                $phone,
                AuthVerificationCode::PURPOSE_REGISTER_OTP,
                AuthVerificationService::REGISTER_TTL_MINUTES,
                $user,
                ['role' => 'driver'],
            );

            return ['user' => $user, 'otp_plain' => $issued['plain']];
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
