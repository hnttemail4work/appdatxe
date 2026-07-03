<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripSettlement;
use App\Models\DriverWallet;
use App\Models\DriverWalletTransaction;
use App\Models\Schedule;
use App\Support\DriverWalletConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverWalletService
{
    public function walletFor(DriverProfile $profile): DriverWallet
    {
        return DriverWallet::query()->firstOrCreate(
            ['driver_profile_id' => $profile->id],
            ['balance' => 0],
        );
    }

    public function onTripCompleted(Booking $booking): ?DriverTripSettlement
    {
        $booking->loadMissing('schedule');

        return $booking->schedule ? $this->onScheduleCompleted($booking->schedule) : null;
    }

    /** Ghi nhận doanh thu chuyến — không còn luồng chuyển phí / kết chuyến. */
    public function onScheduleCompleted(Schedule $schedule): ?DriverTripSettlement
    {
        $schedule->loadMissing('route');

        if (! $schedule->driver_id) {
            return null;
        }

        $existing = DriverTripSettlement::query()->where('schedule_id', $schedule->id)->first();
        if ($existing) {
            return $existing;
        }

        $profile = DriverProfile::query()->where('user_id', $schedule->driver_id)->first();
        if (! $profile) {
            return null;
        }

        $bookings = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', 'completed')
            ->get();

        if ($bookings->isEmpty()) {
            return null;
        }

        $wallet = $this->walletFor($profile);
        $revenue = (int) $bookings->sum(fn (Booking $b): int => (int) round((float) $b->total_price, 0));

        return DB::transaction(function () use ($wallet, $schedule, $bookings, $revenue): DriverTripSettlement {
            $locked = DriverWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $category = $this->resolveSettlementCategory($revenue, $locked);

            $settlement = DriverTripSettlement::query()->create([
                'driver_wallet_id'    => $locked->id,
                'schedule_id'         => $schedule->id,
                'booking_id'          => $bookings->first()->id,
                'revenue_amount'      => $revenue,
                'platform_fee_amount' => 0,
                'category'            => $category,
                'status'              => 'completed',
                'driver_settled_at'   => now(),
            ]);

            $locked->update([
                'cumulative_revenue'          => $locked->cumulative_revenue + $revenue,
                'completed_settlements_count' => $locked->completed_settlements_count + 1,
            ]);

            $locked = $locked->fresh();
            $this->refreshWalletGate($locked);
            $this->refreshAcceptBlock($locked);

            return $settlement;
        });
    }

    public function requestDeposit(DriverProfile $profile, int $amount): DriverWalletTransaction
    {
        if ($amount < DriverWalletConfig::MIN_DEPOSIT) {
            throw new InvalidArgumentException('Số tiền nạp tối thiểu ' . DriverWalletConfig::minDepositFormatted() . '.');
        }

        if (! $profile->operator_id) {
            $this->assignOperatorFromTrips($profile);
        }

        if (! $profile->operator_id) {
            throw new InvalidArgumentException('Tài khoản chưa được gán quản lý — liên hệ quản lý để được duyệt hồ sơ.');
        }

        $wallet = $this->walletFor($profile);

        if ($wallet->transactions()->where('type', 'deposit')->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('Đang có yêu cầu nạp tiền chờ duyệt.');
        }

        return DriverWalletTransaction::query()->create([
            'driver_wallet_id' => $wallet->id,
            'type'             => 'deposit',
            'amount'           => $amount,
            'status'           => 'pending',
            'transfer_ref'     => null,
        ]);
    }

    public function approveDeposit(DriverWalletTransaction $transaction, int $actorId): void
    {
        if ($transaction->type !== 'deposit') {
            throw new InvalidArgumentException('Giao dịch không phải yêu cầu nạp ví.');
        }

        if ($transaction->status !== 'pending') {
            throw new InvalidArgumentException('Giao dịch không còn chờ duyệt.');
        }

        DB::transaction(function () use ($transaction, $actorId): void {
            $wallet = $transaction->wallet()->lockForUpdate()->firstOrFail();
            $profile = $wallet->driverProfile()->lockForUpdate()->firstOrFail();

            $transaction->update([
                'status'      => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            $newBalance = $wallet->balance + $transaction->amount;
            $newTotalDeposits = (int) $wallet->total_approved_deposits + (int) $transaction->amount;

            $walletUpdates = [
                'balance'                 => $newBalance,
                'total_approved_deposits' => $newTotalDeposits,
            ];

            if ($wallet->wallet_activated_at === null
                && $newTotalDeposits >= DriverWalletConfig::ACTIVATION_DEPOSIT) {
                $walletUpdates['wallet_activated_at'] = now();
            }

            $wallet->update($walletUpdates);

            if ($wallet->fresh()->wallet_activated_at && $profile->isApproved()) {
                $profile->update(['availability_status' => 'available']);
            }

            $this->refreshAcceptBlock($wallet->fresh());
        });
    }

    public function enforceDeadlines(): void
    {
        DriverWallet::query()
            ->where('wallet_gate_enabled', true)
            ->where('balance', '<=', DriverWalletConfig::MIN_BALANCE)
            ->each(fn (DriverWallet $wallet) => $this->refreshAcceptBlock($wallet));
    }

    public function canAcceptTrips(DriverProfile $profile): bool
    {
        $profile->loadMissing('user');

        if ($profile->status !== 'active'
            || $profile->isMissedTripLocked()
            || ! $profile->user
            || $profile->user->status !== 'active'
            || $profile->user->role !== 'driver') {
            return false;
        }

        $this->enforceDeadlines();

        return $this->acceptBlockReason($profile) === null;
    }

    public function acceptBlockReason(DriverProfile $profile): ?string
    {
        if (! $profile->isApproved()) {
            return 'Hồ sơ chưa được duyệt.';
        }

        if ($profile->isMissedTripLocked()) {
            return 'Tài khoản đang bị khóa.';
        }

        $wallet = $this->walletFor($profile);

        // Chưa đạt 200k doanh thu — tài xế mới dò/nhận cuốc không cần nạp ví trước.
        if ($this->isPreRevenueThreshold($profile)) {
            return null;
        }

        if (! $wallet->wallet_activated_at) {
            return 'Cần nạp ví tối thiểu ' . DriverWalletConfig::minDepositFormatted() . ' để kích hoạt tài khoản.';
        }

        if ($wallet->wallet_gate_enabled && $wallet->balance <= DriverWalletConfig::MIN_BALANCE) {
            return 'Doanh thu đã đạt ' . DriverWalletConfig::revenueThresholdShortLabel()
                . ' — cần giữ số dư trên ' . DriverWalletConfig::minBalanceFormatted() . ' để nhận cuốc.';
        }

        return null;
    }

    /** Dò cuốc gần — cùng quy tắc ví; không yêu cầu wallet_activated khi chưa đạt ngưỡng doanh thu. */
    public function canDiscoverTrips(DriverProfile $profile): bool
    {
        return $this->acceptBlockReason($profile) === null;
    }

    public function needsTopUpNotice(DriverProfile $profile): bool
    {
        return $this->walletNoticeForDriver($profile) !== null;
    }

    /** Chưa đạt ngưỡng doanh thu 200k — chưa bật cổng ví. */
    public function isPreRevenueThreshold(DriverProfile $profile): bool
    {
        $wallet = $this->walletFor($profile);

        return ! (bool) $wallet->wallet_gate_enabled;
    }

    public function shouldShowTopUpBanner(DriverProfile $profile): bool
    {
        return $this->walletNoticeForDriver($profile) !== null;
    }

    /**
     * Thông báo ví sau ngưỡng doanh thu — nạp kích hoạt hoặc duy trì số dư.
     *
     * @return array{message: string, cta_label: string, cta_tab: string, variant: string}|null
     */
    public function walletNoticeForDriver(DriverProfile $profile): ?array
    {
        $wallet = $this->walletFor($profile);
        $threshold = DriverWalletConfig::revenueThresholdShortLabel();

        if ($wallet->wallet_gate_enabled) {
            if (! $wallet->wallet_activated_at) {
                return [
                    'message'   => 'Doanh thu đã đạt ' . $threshold
                        . ' — cần nạp ví tối thiểu ' . DriverWalletConfig::minDepositFormatted()
                        . ' để kích hoạt và tiếp tục nhận cuốc.',
                    'cta_label' => 'Nạp ví ngay',
                    'cta_tab'   => 'deposit',
                    'variant'   => 'warning',
                ];
            }

            if ($wallet->balance <= DriverWalletConfig::MIN_BALANCE) {
                return [
                    'message'   => 'Doanh thu đã đạt ' . $threshold
                        . ' — cần giữ số dư trên ' . DriverWalletConfig::minBalanceFormatted()
                        . ' để nhận cuốc mới.',
                    'cta_label' => 'Nạp ví ngay',
                    'cta_tab'   => 'deposit',
                    'variant'   => 'warning',
                ];
            }

            return null;
        }

        if ($this->driverLifetimeRevenue($profile) >= DriverWalletConfig::REVENUE_THRESHOLD) {
            return [
                'message'   => 'Doanh thu đã vượt ' . $threshold
                    . ' — cần nạp ví tối thiểu ' . DriverWalletConfig::minDepositFormatted()
                    . ' để tiếp tục nhận cuốc.',
                'cta_label' => 'Nạp ví ngay',
                'cta_tab'   => 'deposit',
                'variant'   => 'warning',
            ];
        }

        return null;
    }

    /** Đồng bộ kết chuyến thiếu và bật cổng ví theo doanh thu tích lũy. */
    public function reconcileWallet(DriverProfile $profile): DriverWallet
    {
        $wallet = $this->walletFor($profile);

        Schedule::query()
            ->where('driver_id', $profile->user_id)
            ->where('status', 'completed')
            ->whereDoesntHave('tripSettlement')
            ->orderBy('id')
            ->each(fn (Schedule $schedule) => $this->onScheduleCompleted($schedule));

        $expectedRevenue = (int) DriverTripSettlement::query()
            ->where('driver_wallet_id', $wallet->id)
            ->where('status', 'completed')
            ->sum('revenue_amount');

        $expectedCount = (int) DriverTripSettlement::query()
            ->where('driver_wallet_id', $wallet->id)
            ->where('status', 'completed')
            ->count();

        if ($wallet->cumulative_revenue !== $expectedRevenue
            || $wallet->completed_settlements_count !== $expectedCount) {
            $wallet->update([
                'cumulative_revenue'          => $expectedRevenue,
                'completed_settlements_count' => $expectedCount,
            ]);
        }

        $wallet = $wallet->fresh();
        $this->refreshWalletGate($wallet);
        $this->refreshAcceptBlock($wallet->fresh());

        return $wallet->fresh();
    }

    public function driverLifetimeRevenue(DriverProfile $profile): int
    {
        return (int) Booking::query()
            ->where('trip_status', 'completed')
            ->whereHas('schedule', fn ($q) => $q->where('driver_id', $profile->user_id))
            ->sum('total_price');
    }

    public function settlementBlockReason(DriverProfile $profile): ?string
    {
        return null;
    }

    public function isWalletActivated(DriverProfile $profile): bool
    {
        return (bool) $this->walletFor($profile)->wallet_activated_at;
    }

    public function driverRevenueBetween(DriverProfile $profile, \Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        $query = Booking::query()
            ->where('trip_status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'assigned_driver_id')) {
            $query->where('assigned_driver_id', $profile->user_id);
        } else {
            $query->whereHas('schedule', fn ($q) => $q->where('driver_id', $profile->user_id));
        }

        return (float) $query->sum('total_price');
    }

    public function driverRevenueStats(DriverProfile $profile): array
    {
        return [
            'day'   => $this->driverRevenueBetween($profile, now()->startOfDay(), now()->endOfDay()),
            'month' => $this->driverRevenueBetween($profile, now()->startOfMonth(), now()->endOfMonth()),
        ];
    }

    /** @return Collection<int, DriverTripSettlement> */
    public function settlementsAwaitingCodeForOperator(int $operatorId): Collection
    {
        return collect();
    }

    /** @return Collection<int, DriverTripSettlement> */
    public function codesAwaitingDriverForOperator(int $operatorId): Collection
    {
        return collect();
    }

    /** @return Collection<int, array{kind: string, amount: int, at: \Carbon\Carbon, label: string, meta: string|null, status: string|null}> */
    public function walletActivityHistory(DriverWallet $wallet): Collection
    {
        $wallet->loadMissing(['transactions', 'settlements.schedule.route']);

        $items = collect();

        foreach ($wallet->transactions as $transaction) {
            $items->push([
                'kind'   => 'deposit',
                'amount' => (int) $transaction->amount,
                'at'     => $transaction->created_at,
                'label'  => 'Nạp ví',
                'meta'   => $transaction->approved_at
                    ? 'Duyệt ' . $transaction->approved_at->format('d/m/Y H:i')
                    : 'Gửi ' . $transaction->created_at->format('d/m/Y H:i'),
                'status' => $transaction->status,
            ]);
        }

        foreach ($wallet->settlements->where('status', 'completed') as $settlement) {
            $schedule = $settlement->schedule;
            $routeMeta = $schedule
                ? $schedule->route->departure . ' → ' . $schedule->route->destination
                : null;

            $items->push([
                'kind'   => 'trip_revenue',
                'amount' => (int) $settlement->revenue_amount,
                'at'     => $settlement->driver_settled_at ?? $settlement->updated_at,
                'label'  => 'Hoàn thành chuyến',
                'meta'   => $routeMeta,
                'status' => 'completed',
            ]);
        }

        return $items
            ->filter(fn (array $item): bool => $item['at'] !== null)
            ->sortByDesc(fn (array $item) => $item['at']->timestamp)
            ->values();
    }

    /** @return Collection<int, array{kind: string, amount: int, at: \Carbon\Carbon, label: string, meta: string|null, status: string|null, driver_name: string, driver_code: string|null}> */
    public function operatorWalletActivityHistory(int $operatorId, int $limit = 80): Collection
    {
        $items = collect();

        $transactions = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->whereHas('wallet.driverProfile', fn ($q) => $q->managedByOperator($operatorId))
            ->latest()
            ->limit($limit)
            ->get();

        foreach ($transactions as $transaction) {
            $profile = $transaction->wallet->driverProfile;
            $items->push([
                'kind'        => 'deposit',
                'amount'      => (int) $transaction->amount,
                'at'          => $transaction->created_at,
                'label'       => 'Nạp ví',
                'meta'        => $transaction->approved_at
                    ? 'Duyệt ' . $transaction->approved_at->format('d/m/Y H:i')
                    : 'Gửi ' . $transaction->created_at->format('d/m/Y H:i'),
                'status'      => $transaction->status,
                'driver_name' => $profile->user->name,
                'driver_code' => $profile->driver_code,
            ]);
        }

        return $items
            ->filter(fn (array $item): bool => $item['at'] !== null)
            ->sortByDesc(fn (array $item) => $item['at']->timestamp)
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, DriverWalletTransaction> */
    public function pendingDepositsForOperator(int $operatorId): Collection
    {
        return DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->whereHas('wallet.driverProfile', fn ($q) => $q->managedByOperator($operatorId))
            ->latest()
            ->get();
    }

    /** @return array{deposits: int, settlements: int, total: int} */
    public function pendingWalletRequestCounts(int $operatorId): array
    {
        $deposits = $this->pendingDepositsForOperator($operatorId)->count();

        return [
            'deposits'    => $deposits,
            'settlements' => 0,
            'total'       => $deposits,
        ];
    }

    /** @return Collection<int, DriverWalletTransaction> */
    public function pendingDepositsForDriver(DriverProfile $profile): Collection
    {
        $wallet = $this->walletFor($profile);

        return DriverWalletTransaction::query()
            ->where('driver_wallet_id', $wallet->id)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    private function resolveSettlementCategory(int $revenue, DriverWallet $wallet): string
    {
        if ($revenue < DriverWalletConfig::REVENUE_THRESHOLD) {
            return 'under_threshold';
        }

        if ($wallet->cumulative_revenue < DriverWalletConfig::REVENUE_THRESHOLD) {
            return 'first_over_threshold';
        }

        return 'over_threshold';
    }

    private function refreshWalletGate(DriverWallet $wallet): void
    {
        if ($wallet->wallet_gate_enabled) {
            return;
        }

        if ($wallet->cumulative_revenue >= DriverWalletConfig::REVENUE_THRESHOLD) {
            $wallet->update(['wallet_gate_enabled' => true]);
        }
    }

    private function refreshAcceptBlock(DriverWallet $wallet): void
    {
        $wallet->refresh();
        $profile = $wallet->driverProfile;

        if (! $profile) {
            return;
        }

        if (! $wallet->wallet_activated_at) {
            $wallet->update([
                'accept_trips_blocked_at'   => now(),
                'accept_trips_block_reason' => 'not_activated',
            ]);

            return;
        }

        if ($wallet->wallet_gate_enabled && $wallet->balance <= DriverWalletConfig::MIN_BALANCE) {
            $wallet->update([
                'accept_trips_blocked_at'   => now(),
                'accept_trips_block_reason' => 'low_balance',
            ]);

            return;
        }

        $wallet->update([
            'accept_trips_blocked_at'   => null,
            'accept_trips_block_reason' => null,
        ]);
    }

    private function assignOperatorFromTrips(DriverProfile $profile): void
    {
        $operatorId = Schedule::query()
            ->where('driver_id', $profile->user_id)
            ->whereHas('vehicle', fn ($q) => $q->whereNotNull('operator_id'))
            ->with('vehicle:id,operator_id')
            ->latest('id')
            ->get()
            ->pluck('vehicle.operator_id')
            ->filter()
            ->first();

        if ($operatorId) {
            $profile->update(['operator_id' => (int) $operatorId]);
            $profile->refresh();
        }
    }
}
