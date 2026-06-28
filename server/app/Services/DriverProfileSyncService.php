<?php

namespace App\Services;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DriverProfileSyncService
{
    /** Đồng bộ trạng thái users.status ↔ driver_profiles.status */
    public function setAccountStatus(DriverProfile $profile, string $status): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $status): void {
            $profileUpdates = ['status' => $status];

            if ($status === 'active' && empty($profile->availability_status)) {
                $profileUpdates['availability_status'] = 'available';
            }

            $profile->update($profileUpdates);
            $profile->user->update(['status' => $status]);
        });
    }

    public function approve(DriverProfile $profile, ?int $operatorId = null): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $operatorId): void {
            $updates = [
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => 'available',
            ];

            if ($operatorId !== null && $profile->operator_id === null) {
                $updates['operator_id'] = $operatorId;
            }

            $profile->update($updates);
            $profile->user->update(['status' => 'active']);
        });
    }

    public function reject(DriverProfile $profile): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile): void {
            $profile->update([
                'status'           => 'inactive',
                'approval_status'  => 'rejected',
            ]);
            $profile->user->update(['status' => 'inactive']);
        });
    }

    /** @return list<string> */
    public static function profileFieldKeys(): array
    {
        return [
            'license_number', 'license_class', 'license_expiry', 'experience_years',
            'notes', 'bank_name', 'bank_account',
            'vehicle_license_plate', 'vehicle_type', 'vehicle_brand', 'vehicle_model',
            'vehicle_color', 'vehicle_seats',
            'status', 'availability_status',
        ];
    }

    public function applyUnifiedStatus(DriverProfile $profile, string $unified): void
    {
        if (in_array($unified, ['available', 'on_trip', 'off_duty'], true)) {
            $this->setAccountStatus($profile, 'active');
            $profile->update(['availability_status' => $unified]);

            return;
        }

        if ($unified === 'suspended') {
            $this->setAccountStatus($profile, 'suspended');

            return;
        }

        if ($unified === 'inactive') {
            $this->setAccountStatus($profile, 'inactive');
        }
    }

    /** @param  array<string, mixed>  $validated */
    public function fillProfileFromValidated(DriverProfile $profile, array $validated): void
    {
        $profile->loadMissing('user');

        $data = collect($validated)
            ->only(self::profileFieldKeys())
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        $status = $data['status'] ?? null;
        unset($data['status']);

        if ($data !== []) {
            $profile->update($data);
        }

        if ($status !== null) {
            $this->setAccountStatus($profile, $status);
        }
    }

    /** @param  array<string, mixed>  $validated */
    public function fillUserFromValidated(DriverProfile $profile, array $validated): void
    {
        $userData = collect($validated)
            ->only(['name', 'email', 'phone', 'address', 'id_number', 'date_of_birth', 'password'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        if ($userData !== []) {
            $profile->user->update($userData);
        }
    }
}
