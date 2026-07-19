<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Models\DriverProfile;
use App\Models\User;
use App\Support\AuthOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hết TTL chờ duyệt → chuyển "Đã từ chối" (khách + tài xế).
 * Xóa hẳn record chỉ khi admin bulk-delete hoặc SĐT đăng ký lại.
 */
class PendingApprovalExpiryService
{
    public function __construct(
        private readonly DriverProfileSyncService $driverSync,
    ) {
    }

    public function expireCustomer(User $user): bool
    {
        if (! $user->isCustomerApprovalPending() || ! $user->isCustomerPendingApprovalExpired()) {
            return false;
        }

        $user->update([
            'status'              => 'inactive',
            'approval_status'     => User::APPROVAL_REJECTED,
            'rejection_reason'    => AuthOtp::pendingExpiredRejectionReason(),
            'rejection_reason_at' => now(),
        ]);

        return true;
    }

    public function expireDriver(DriverProfile $profile): bool
    {
        if (! $profile->isPendingApprovalExpired()) {
            return false;
        }

        $this->driverSync->reject($profile, AuthOtp::pendingExpiredRejectionReason());

        return true;
    }

    public function expireStaleCustomers(): int
    {
        $count = 0;
        User::query()
            ->where('role', 'customer')
            ->where('approval_status', User::APPROVAL_PENDING)
            ->orderBy('id')
            ->each(function (User $user) use (&$count): void {
                if ($this->expireCustomer($user)) {
                    $count++;
                }
            });

        return $count;
    }

    public function expireStaleDrivers(): int
    {
        $count = 0;
        DriverProfile::query()
            ->pendingApproval()
            ->with('user')
            ->orderBy('id')
            ->each(function (DriverProfile $profile) use (&$count): void {
                if ($this->expireDriver($profile)) {
                    $count++;
                }
            });

        return $count;
    }

    /** Xóa hẳn hồ sơ khách đã từ chối (admin bulk hoặc nhường SĐT khi đăng ký lại). */
    public function deleteCustomerRegistration(User $user): bool
    {
        if (! $user->isCustomer() || ! $user->isCustomerApprovalRejected()) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            foreach (CustomerDocumentService::idCardFields() as $field) {
                $path = $user->{$field} ?? null;
                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }

            Storage::disk('public')->deleteDirectory('customers/'.$user->id);

            AuthVerificationCode::query()
                ->where('user_id', $user->id)
                ->delete();

            $user->delete();
        });

        return true;
    }

    /** Xóa hẳn hồ sơ tài xế đã từ chối (admin bulk hoặc dọn slot đăng ký). */
    public function deleteDriverRegistration(DriverProfile $profile): bool
    {
        $profile->loadMissing('user');

        if (! $profile->isRejected()) {
            return false;
        }

        $user = $profile->user;
        if (! $user || $user->role !== 'driver') {
            return false;
        }

        DB::transaction(function () use ($profile, $user): void {
            app(DriverCatalogService::class)->deactivateCatalogForDriver($profile);

            foreach ([
                'photo_portrait', 'photo_id_card', 'photo_id_card_back',
                'photo_license_front', 'photo_license_back', 'photo_vehicle',
            ] as $field) {
                $path = $profile->{$field} ?? null;
                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }

            $vehicles = $profile->photo_vehicles;
            if (is_array($vehicles)) {
                foreach ($vehicles as $path) {
                    if (is_string($path) && $path !== '') {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            Storage::disk('public')->deleteDirectory('drivers/'.$profile->id);

            AuthVerificationCode::query()
                ->where('user_id', $user->id)
                ->delete();

            $profile->delete();
            $user->delete();
        });

        return true;
    }
}
