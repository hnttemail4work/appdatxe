<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RegistrationService
{
    /** @return array<string, mixed> */
    public function customerRules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'           => ['required', 'string', 'max:30'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
            'id_number'       => ['nullable', 'string', 'max:20'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'address'         => ['nullable', 'string', 'max:255'],
            'terms'           => ['accepted'],
        ];
    }

    /** @return array<string, mixed> */
    public function driverRules(): array
    {
        return array_merge($this->customerRules(), [
            'phone'              => ['required', 'string', 'max:30'],
            'id_number'          => ['required', 'string', 'max:20'],
            'address'            => ['required', 'string', 'max:255'],
            'license_number'     => ['required', 'string', 'max:50', 'unique:driver_profiles,license_number'],
            'license_class'      => ['required', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'     => ['required', 'date', 'after:today'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'bank_account'       => ['nullable', 'string', 'max:50'],
        ]);
    }

    public function registerCustomer(array $validated): User
    {
        return User::query()->create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'phone'         => $validated['phone'],
            'id_number'     => $validated['id_number'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'address'       => $validated['address'] ?? null,
            'role'          => 'customer',
            'status'        => 'active',
        ]);
    }

    public function registerDriver(array $validated): User
    {
        return DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'password'      => Hash::make($validated['password']),
                'phone'         => $validated['phone'],
                'id_number'     => $validated['id_number'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'address'       => $validated['address'],
                'role'          => 'driver',
                'status'        => 'inactive',
            ]);

            DriverProfile::query()->create([
                'user_id'             => $user->id,
                'operator_id'         => null,
                'license_number'      => $validated['license_number'],
                'license_class'       => $validated['license_class'],
                'license_expiry'      => $validated['license_expiry'],
                'experience_years'    => $validated['experience_years'] ?? 0,
                'status'              => 'inactive',
                'availability_status' => 'off_duty',
                'notes'               => $validated['notes'] ?? null,
                'bank_name'           => $validated['bank_name'] ?? null,
                'bank_account'        => $validated['bank_account'] ?? null,
            ]);

            return $user;
        });
    }
}
