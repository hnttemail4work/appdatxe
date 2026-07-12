<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use App\Support\AuthIdentifier;
use App\Support\DriverFieldRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
