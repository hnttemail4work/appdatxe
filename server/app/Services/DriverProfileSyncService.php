<?php

namespace App\Services;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;

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

            if (in_array($status, ['suspended', 'inactive'], true)) {
                $profileUpdates['availability_status'] = 'off_duty';
            }

            $profile->update($profileUpdates);
            $profile->user->update(['status' => $status]);
        });

        $profile->refresh();
        if ($status === 'active') {
            app(DriverCatalogService::class)->syncCatalogForDriver($profile);
        } else {
            app(DriverCatalogService::class)->deactivateCatalogForDriver($profile);
        }
    }

    /**
     * @param  array<string, mixed>|null  $identity  name / date_of_birth / gender / id_number
     */
    public function approve(DriverProfile $profile, ?int $operatorId = null, ?array $identity = null): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $operatorId, $identity): void {
            $updates = [
                'status'              => 'active',
                'approval_status'     => 'approved',
                'availability_status' => $profile->isWalletActivated() ? 'available' : 'off_duty',
            ];

            if ($operatorId !== null && $profile->operator_id === null) {
                $updates['operator_id'] = $operatorId;
            }

            $profile->update($updates);

            $userData = ['status' => 'active'];
            if (is_array($identity) && $identity !== []) {
                $userData = array_merge($userData, $identity);
            }
            $profile->user->update($userData);
        });

        app(DriverCatalogService::class)->syncCatalogForDriver($profile->fresh());
    }

    public function reject(DriverProfile $profile, ?string $reason = null): void
    {
        $profile->loadMissing('user');

        DB::transaction(function () use ($profile, $reason): void {
            $profile->update([
                'status'              => 'inactive',
                'approval_status'     => 'rejected',
                'rejection_reason'    => filled($reason) ? trim($reason) : null,
                'rejection_reason_at' => filled($reason) ? now() : null,
            ]);
            $profile->user->update(['status' => 'inactive']);
        });

        app(DriverCatalogService::class)->deactivateCatalogForDriver($profile->fresh());
    }

    public function clearRejectionNote(DriverProfile $profile): void
    {
        $profile->update([
            'rejection_reason'    => null,
            'rejection_reason_at' => null,
        ]);
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

        $syncSeatsFromType = array_key_exists('vehicle_type', $validated) && filled($validated['vehicle_type']);
        if ($syncSeatsFromType) {
            unset($data['vehicle_seats']);
        }

        if ($data !== []) {
            $profile->update($data);
        }

        if ($syncSeatsFromType) {
            $profile->update([
                'vehicle_seats' => \App\Support\DriverVehicleOptions::seatsFor((string) $validated['vehicle_type']),
            ]);
        }

        if ($status !== null) {
            $this->setAccountStatus($profile, $status);
        }

        $profile->refresh();
        app(DriverCatalogService::class)->syncCatalogForDriver($profile);
    }

    /** @param  array<string, mixed>  $validated */
    public function fillUserFromValidated(DriverProfile $profile, array $validated): void
    {
        $userData = [];

        if (array_key_exists('name', $validated) && $validated['name'] !== null && $validated['name'] !== '') {
            $userData['name'] = $validated['name'];
        }

        if (array_key_exists('phone', $validated) && $validated['phone'] !== null && $validated['phone'] !== '') {
            $userData['phone'] = $validated['phone'];
        }

        if (array_key_exists('email', $validated)) {
            $userData['email'] = filled($validated['email'])
                ? trim((string) $validated['email'])
                : null;
        }

        foreach (['address', 'id_number', 'date_of_birth', 'gender'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null && $validated[$field] !== '') {
                $userData[$field] = $validated[$field];
            }
        }

        if ($userData !== []) {
            $profile->user->update($userData);
            app(DriverCatalogService::class)->syncCatalogForDriver($profile->fresh());
        }
    }
}
