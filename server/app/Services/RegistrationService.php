<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use App\Rules\UniqueNormalizedPhone;
use App\Services\CustomerAccountService;
use App\Support\AuthIdentifier;
use App\Support\DriverFieldRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegistrationService
{
    public function __construct(private readonly DriverPhotoService $photos)
    {
    }

    /** @return array<string, mixed> */
    public function driverRules(): array
    {
        return DriverFieldRules::registrationRules();
    }

    /** @return array<string, mixed> */
    public function customerRules(): array
    {
        return [
            'phone'                 => ['required', 'string', 'max:30', new UniqueNormalizedPhone()],
            'email'                 => ['nullable', 'email', 'max:255'],
            'password'              => ['required', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function registerCustomer(array $validated): User
    {
        return DB::transaction(function () use ($validated): User {
            $phone = AuthIdentifier::normalizePhone((string) $validated['phone']);
            $email = filled($validated['email'] ?? null)
                ? trim((string) $validated['email'])
                : null;
            $displayName = 'Khách ' . substr($phone, -4);

            $user = User::query()->create([
                'name'     => $displayName,
                'email'    => $email,
                'password' => Hash::make($validated['password']),
                'phone'    => $phone,
                'role'     => 'customer',
                'status'   => 'active',
            ]);

            app(CustomerAccountService::class)->linkExistingBookings($user);

            return $user;
        });
    }

    public function registerDriver(array $validated, Request $request): User
    {
        return DB::transaction(function () use ($validated, $request): User {
            $email = filled($validated['email'] ?? null)
                ? trim((string) $validated['email'])
                : null;

            $user = User::query()->create([
                'name'          => $validated['name'],
                'email'         => $email,
                'password'      => Hash::make($validated['password']),
                'phone'         => AuthIdentifier::normalizePhone((string) $validated['phone']),
                'id_number'     => $validated['id_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'address'       => $validated['address'] ?? null,
                'role'          => 'driver',
                'status'        => 'inactive',
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
                'vehicle_seats'         => $validated['vehicle_seats'],
            ]);

            $this->photos->storeRegistrationPhotos($profile, $request);

            return $user;
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
