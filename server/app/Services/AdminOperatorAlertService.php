<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CustomerProfileChangeRequest;
use App\Models\CustomerWalletTransaction;
use App\Models\DriverProfile;
use App\Models\DriverProfileChangeRequest;
use App\Models\DriverWalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminOperatorAlertService
{
    private const CACHE_KEY = 'admin_operator_alerts';

    private const MAX_ALERTS = 40;

    /** Đã tắt: nhận cuốc không còn bắn thông báo cho admin. */
    public function recordDriverAccepted(Booking $booking): void
    {
        // no-op
    }

    public function recordCustomerRegistrationPending(User $user): void
    {
        $phone = trim((string) ($user->phone ?? ''));
        $label = $phone !== '' ? $phone : ('#' . $user->id);

        $this->push([
            'id'      => 'customer_reg:' . $user->id . ':' . now()->timestamp,
            'type'    => 'customer_registration_pending',
            'title'   => 'Khách chờ duyệt',
            'message' => 'Hồ sơ mới ' . $label . ' — vào Chờ duyệt để xác minh CCCD.',
            'variant' => 'warning',
            'url'     => route('admin.users.edit', $user),
            'at'      => now()->toIso8601String(),
        ]);
    }

    public function recordDriverRegistrationPending(DriverProfile $profile): void
    {
        $profile->loadMissing('user');
        $name = trim((string) ($profile->user?->name ?? ''));
        $phone = trim((string) ($profile->user?->phone ?? ''));
        $label = $name !== '' ? $name : ($phone !== '' ? $phone : ('#' . $profile->id));

        $this->push([
            'id'      => 'driver_reg:' . $profile->id . ':' . now()->timestamp,
            'type'    => 'driver_registration_pending',
            'title'   => 'Tài xế chờ duyệt',
            'message' => 'Hồ sơ mới ' . $label . ' — vào Chờ duyệt để xét duyệt.',
            'variant' => 'warning',
            'url'     => route('admin.drivers.edit', $profile),
            'at'      => now()->toIso8601String(),
        ]);
    }

    public function recordCustomerProfileChangePending(CustomerProfileChangeRequest $change): void
    {
        $change->loadMissing('user');
        $user = $change->user;
        $phone = trim((string) ($user?->phone ?? ''));
        $name = trim((string) ($user?->preferredDisplayName() ?? $user?->name ?? ''));
        $label = $name !== '' && $name !== $phone ? $name : ($phone !== '' ? $phone : ('#' . $change->user_id));

        $this->push([
            'id'      => 'customer_change:' . $change->id . ':' . now()->timestamp,
            'type'    => 'customer_profile_change_pending',
            'title'   => 'Khách cập nhật hồ sơ',
            'message' => $label . ' gửi yêu cầu cập nhật — cần duyệt.',
            'variant' => 'warning',
            'url'     => $user ? route('admin.users.edit', ['user' => $user, 'tab' => 'requests']) : route('admin.users'),
            'at'      => now()->toIso8601String(),
        ]);
    }

    public function recordDriverProfileChangePending(DriverProfileChangeRequest $change): void
    {
        $change->loadMissing('profile.user');
        $profile = $change->profile;
        $name = trim((string) ($profile?->user?->name ?? ''));
        $phone = trim((string) ($profile?->user?->phone ?? ''));
        $label = $name !== '' ? $name : ($phone !== '' ? $phone : ('#' . $change->driver_profile_id));

        $this->push([
            'id'      => 'driver_change:' . $change->id . ':' . now()->timestamp,
            'type'    => 'driver_profile_change_pending',
            'title'   => 'Tài xế cập nhật giấy tờ',
            'message' => $label . ' gửi yêu cầu cập nhật — cần duyệt.',
            'variant' => 'warning',
            'url'     => $profile
                ? route('admin.drivers.edit', ['driverProfile' => $profile, 'tab' => 'requests'])
                : route('admin.drivers'),
            'at'      => now()->toIso8601String(),
        ]);
    }

    public function recordDriverDepositPending(DriverWalletTransaction $transaction): void
    {
        $transaction->loadMissing('wallet.driverProfile.user');
        $profile = $transaction->wallet?->driverProfile;
        $name = trim((string) ($profile?->user?->name ?? 'Tài xế'));
        $amount = number_format((int) $transaction->amount, 0, ',', '.') . ' đ';

        $this->push([
            'id'      => 'driver_deposit:' . $transaction->id . ':' . now()->timestamp,
            'type'    => 'driver_deposit_pending',
            'title'   => 'Nạp ví TX chờ duyệt',
            'message' => $name . ' gửi nạp ' . $amount . '.',
            'variant' => 'warning',
            'url'     => route('admin.walletDeposits'),
            'at'      => now()->toIso8601String(),
        ]);
    }

    public function recordCustomerDepositPending(CustomerWalletTransaction $transaction): void
    {
        $transaction->loadMissing('wallet.user');
        $user = $transaction->wallet?->user;
        $phone = trim((string) ($user?->phone ?? ''));
        $name = trim((string) ($user?->preferredDisplayName() ?? $user?->name ?? ''));
        $label = $name !== '' && $name !== $phone ? $name : ($phone !== '' ? $phone : 'Khách');
        $amount = number_format((int) $transaction->amount, 0, ',', '.') . ' đ';

        $this->push([
            'id'      => 'customer_deposit:' . $transaction->id . ':' . now()->timestamp,
            'type'    => 'customer_deposit_pending',
            'title'   => 'Nạp ví KH chờ duyệt',
            'message' => $label . ' gửi nạp ' . $amount . '.',
            'variant' => 'warning',
            'url'     => route('admin.walletDeposits'),
            'at'      => now()->toIso8601String(),
        ]);
    }

    /** @param array<string, mixed> $alert */
    private function push(array $alert): void
    {
        $alerts = Cache::get(self::CACHE_KEY, []);
        if (! is_array($alerts)) {
            $alerts = [];
        }

        $alerts[] = $alert;

        if (count($alerts) > self::MAX_ALERTS) {
            $alerts = array_slice($alerts, -self::MAX_ALERTS);
        }

        Cache::put(self::CACHE_KEY, $alerts, now()->addHours(2));
    }

    /** @return list<array<string, mixed>> */
    public function pullAlerts(): array
    {
        $alerts = Cache::pull(self::CACHE_KEY, []);

        return is_array($alerts) ? array_values($alerts) : [];
    }
}
