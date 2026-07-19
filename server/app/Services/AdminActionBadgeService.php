<?php

namespace App\Services;

use App\Models\CustomerProfileChangeRequest;
use App\Models\CustomerWalletTransaction;
use App\Models\DriverProfile;
use App\Models\DriverProfileChangeRequest;
use App\Models\DriverWalletTransaction;
use App\Models\User;

/**
 * Số trên nav / tab Chờ duyệt = tổng việc đang chờ.
 * Khi đang mở đúng trang đó thì ẩn số (đã xem).
 */
class AdminActionBadgeService
{
    public function usersBadgeCount(): int
    {
        $customers = User::query()
            ->where('role', 'customer')
            ->where('approval_status', User::APPROVAL_PENDING)
            ->count();

        $changes = CustomerProfileChangeRequest::query()
            ->where('status', CustomerProfileChangeRequest::STATUS_PENDING)
            ->count();

        return $customers + $changes;
    }

    public function driversBadgeCount(): int
    {
        $drivers = DriverProfile::query()->pendingApproval()->count();

        $changes = DriverProfileChangeRequest::query()
            ->where('status', DriverProfileChangeRequest::STATUS_PENDING)
            ->count();

        return $drivers + $changes;
    }

    public function driverDepositsBadgeCount(): int
    {
        return DriverWalletTransaction::query()
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->count();
    }

    public function customerDepositsBadgeCount(): int
    {
        return CustomerWalletTransaction::query()
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->count();
    }

    public function walletDepositsBadgeCount(): int
    {
        return $this->driverDepositsBadgeCount() + $this->customerDepositsBadgeCount();
    }

    /** Đang xem trang xử lý mục này → không hiện badge. */
    public function usersBadgeVisible(): bool
    {
        if (! request()->routeIs('admin.users', 'admin.users.edit')) {
            return true;
        }

        if (request()->routeIs('admin.users.edit')) {
            return false;
        }

        return request('status') !== 'pending';
    }

    public function driversBadgeVisible(): bool
    {
        if (! request()->routeIs('admin.drivers', 'admin.drivers.edit')) {
            return true;
        }

        if (request()->routeIs('admin.drivers.edit')) {
            return false;
        }

        return request('filter') !== 'pending';
    }

    public function walletDepositsBadgeVisible(): bool
    {
        return ! request()->routeIs('admin.walletDeposits', 'admin.driverWallet', 'admin.customerWallet');
    }
}
