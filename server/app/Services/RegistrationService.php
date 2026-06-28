<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\MerchantProfile;
use App\Models\User;
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

    /** @return array<string, mixed> */
    public function operatorRules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function registerOperator(array $validated, int $approvedBy): User
    {
        return DB::transaction(function () use ($validated, $approvedBy): User {
            $user = User::query()->create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone'    => $validated['phone'] ?? null,
                'role'     => 'operator',
                'status'   => 'active',
            ]);

            MerchantProfile::query()->create([
                'user_id'      => $user->id,
                'company_name' => $validated['name'],
                'kyc_status'   => 'approved',
                'approved_by'  => $approvedBy,
                'approved_at'  => now(),
            ]);

            return $user;
        });
    }

    public function registerDriver(array $validated, Request $request): User
    {
        return DB::transaction(function () use ($validated, $request): User {
            $phoneDigits = preg_replace('/\D/', '', $validated['phone']);
            $email = filled($validated['email'] ?? null)
                ? $validated['email']
                : $this->placeholderEmail($phoneDigits);

            $user = User::query()->create([
                'name'          => $validated['name'],
                'email'         => $email,
                'password'      => Hash::make(Str::password(16)),
                'phone'         => $validated['phone'],
                'id_number'     => $validated['id_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'address'       => $validated['address'] ?? null,
                'role'          => 'driver',
                'status'        => 'inactive',
            ]);

            $licenseNumber = $this->resolveLicenseNumber($phoneDigits);
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

    private function placeholderEmail(string $phoneDigits): string
    {
        $base = 'tx.' . ($phoneDigits !== '' ? $phoneDigits : Str::lower(Str::random(8)));
        $email = $base . '@noemail.local';
        $suffix = 0;

        while (User::query()->where('email', $email)->exists()) {
            $suffix++;
            $email = $base . '.' . $suffix . '@noemail.local';
        }

        return $email;
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
