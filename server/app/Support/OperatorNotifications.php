<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Services\DriverWalletService;

class OperatorNotifications
{
    /**
     * @return array<int, array{message: string, href: string}>
     */
    public static function list(int $operatorId): array
    {
        $items = [];

        $pendingDrivers = DriverProfile::pendingCountForOperator($operatorId);
        if ($pendingDrivers > 0) {
            $items[] = [
                'message' => $pendingDrivers === 1
                    ? '1 hồ sơ tài xế chờ duyệt'
                    : "{$pendingDrivers} hồ sơ tài xế chờ duyệt",
                'href' => route('operator.drivers'),
            ];
        }

        $pendingBookings = Booking::query()
            ->where('booking_status', 'pending')
            ->whereNull('expired_at')
            ->whereHas('schedule', function ($q) use ($operatorId): void {
                $q->whereNull('driver_id')
                    ->where('departure_time', '>', now())
                    ->whereHas('vehicle', fn ($v) => $v->where('operator_id', $operatorId));
            })
            ->count();

        if ($pendingBookings > 0) {
            $items[] = [
                'message' => $pendingBookings === 1
                    ? '1 đặt xe chờ xử lý'
                    : "{$pendingBookings} đặt xe chờ xử lý",
                'href' => route('operator.dashboard', ['tab' => 'bookings']),
            ];
        }

        $wallet = app(DriverWalletService::class);
        $awaitingSettle = $wallet->settlementsAwaitingCodeForOperator($operatorId)->count();
        if ($awaitingSettle > 0) {
            $items[] = [
                'message' => $awaitingSettle === 1
                    ? '1 chuyến chờ cấp mã kết chuyến'
                    : "{$awaitingSettle} chuyến chờ cấp mã kết chuyến",
                'href' => route('operator.driverWallet'),
            ];
        }

        $pendingDeposits = $wallet->pendingDepositsForOperator($operatorId)->count();
        if ($pendingDeposits > 0) {
            $items[] = [
                'message' => $pendingDeposits === 1
                    ? '1 yêu cầu nạp ví chờ duyệt'
                    : "{$pendingDeposits} yêu cầu nạp ví chờ duyệt",
                'href' => route('operator.driverWallet'),
            ];
        }

        return $items;
    }
}
