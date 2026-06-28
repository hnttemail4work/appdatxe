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

        if (! $booking->schedule) {
            return null;
        }

        return $this->onScheduleCompleted($booking->schedule);
    }

    /** Một bản ghi kết chuyến cho cả chuyến xe (gom mọi vé). */
    public function onScheduleCompleted(\App\Models\Schedule $schedule): ?DriverTripSettlement
    {
        $schedule->loadMissing('route');

        if (! $schedule->driver_id) {
            return null;
        }

        $existing = DriverTripSettlement::query()->where('schedule_id', $schedule->id)->first();
        if ($existing) {
            return $existing;
        }

        $profile = DriverProfile::query()
            ->where('user_id', $schedule->driver_id)
            ->first();

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
        $fee = (int) $bookings->sum(fn (Booking $b): int => DriverWalletConfig::platformFee((int) round((float) $b->total_price, 0)));
        $category = $this->resolveCategory($wallet, $revenue);

        return DriverTripSettlement::query()->create([
            'driver_wallet_id'    => $wallet->id,
            'schedule_id'         => $schedule->id,
            'booking_id'          => $bookings->first()->id,
            'revenue_amount'      => $revenue,
            'platform_fee_amount' => $fee,
            'category'            => $category,
            'status'              => 'pending_settle',
        ]);
    }

    /** Tài xế nhập mã kết chuyến do quản lý cấp sau khi đã chuyển phí nền tảng. */
    public function settleTrip(DriverTripSettlement $settlement, ?string $code): void
    {
        if ($settlement->status !== 'pending_driver_code') {
            throw new InvalidArgumentException(
                $settlement->status === 'pending_settle'
                    ? 'Chưa có mã kết chuyến. Chuyển phí nền tảng cho công ty rồi liên hệ quản lý nhận mã.'
                    : 'Chuyến này không còn ở trạng thái chờ kết.'
            );
        }

        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            throw new InvalidArgumentException('Vui lòng nhập mã kết chuyến.');
        }

        if ($settlement->settlementCodeExpired()) {
            throw new InvalidArgumentException('Mã kết chuyến đã hết hạn (1 ngày). Liên hệ quản lý cấp mã mới.');
        }

        if (! $settlement->settlementCodeIsValid($code)) {
            throw new InvalidArgumentException('Mã kết chuyến không đúng.');
        }

        DB::transaction(function () use ($settlement): void {
            $wallet = $settlement->wallet()->lockForUpdate()->firstOrFail();

            $settlement->update([
                'status'            => 'completed',
                'driver_settled_at' => now(),
            ]);

            $this->finalizeSettlement($settlement->fresh());
            $this->refreshAcceptBlock($wallet->fresh());
        });
    }

    /** Quản lý cấp mã kết chuyến sau khi đã nhận phí nền tảng từ tài xế. */
    public function issueSettlementCode(DriverTripSettlement $settlement, int $operatorId): string
    {
        if ($settlement->status !== 'pending_settle') {
            throw new InvalidArgumentException('Chuyến không ở trạng thái chờ cấp mã.');
        }

        if (! $settlement->transfer_ref) {
            throw new InvalidArgumentException('Tài xế chưa xác nhận chuyển phí.');
        }

        $settlement->loadMissing('wallet.driverProfile');
        if ((int) $settlement->wallet->driverProfile->operator_id !== $operatorId) {
            throw new InvalidArgumentException('Không có quyền cấp mã cho tài xế này.');
        }

        $code = $this->generateSettlementCode();
        $expiresAt = now()->addHours(DriverWalletConfig::SETTLEMENT_CODE_TTL_HOURS);

        $settlement->update([
            'status'                      => 'pending_driver_code',
            'settlement_code'             => $code,
            'settlement_code_expires_at'  => $expiresAt,
            'operator_code_issued_at'     => now(),
            'operator_code_issued_by'     => $operatorId,
        ]);

        return $code;
    }

    public function requestDeposit(DriverProfile $profile, int $amount, string $transferRef): DriverWalletTransaction
    {
        if ($amount < DriverWalletConfig::MIN_BALANCE) {
            throw new InvalidArgumentException('Số tiền nạp tối thiểu ' . number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') . ' đ.');
        }

        $transferRef = trim($transferRef);
        if ($transferRef === '') {
            throw new InvalidArgumentException('Vui lòng nhập mã tham chiếu chuyển khoản.');
        }

        $wallet = $this->walletFor($profile);

        if (! $wallet->wallet_gate_enabled) {
            throw new InvalidArgumentException('Ví chưa kích hoạt — hoàn tất kết chuyến đầu tiên có doanh thu ≥ 500k trước.');
        }

        if ($wallet->transactions()->where('status', 'pending')->exists()) {
            throw new InvalidArgumentException('Đang có yêu cầu nạp tiền chờ duyệt.');
        }

        return DriverWalletTransaction::query()->create([
            'driver_wallet_id' => $wallet->id,
            'type'             => 'deposit',
            'amount'           => $amount,
            'status'           => 'pending',
            'transfer_ref'     => $transferRef,
        ]);
    }

    public function approveDeposit(DriverWalletTransaction $transaction, int $actorId): void
    {
        if ($transaction->status !== 'pending') {
            throw new InvalidArgumentException('Giao dịch không còn chờ duyệt.');
        }

        DB::transaction(function () use ($transaction, $actorId): void {
            $wallet = $transaction->wallet()->lockForUpdate()->firstOrFail();

            $transaction->update([
                'status'      => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            $wallet->update([
                'balance' => $wallet->balance + $transaction->amount,
            ]);

            $this->refreshAcceptBlock($wallet->fresh());
        });
    }

    public function enforceDeadlines(): void
    {
        DriverWallet::query()
            ->where('wallet_gate_enabled', true)
            ->where('balance', '<=', DriverWalletConfig::MIN_BALANCE)
            ->each(fn (DriverWallet $wallet) => $this->refreshAcceptBlock($wallet));

        DriverTripSettlement::query()
            ->where('status', 'pending_driver_code')
            ->where('settlement_code_expires_at', '<', now())
            ->each(function (DriverTripSettlement $settlement): void {
                $settlement->update([
                    'status'                     => 'pending_settle',
                    'settlement_code'            => null,
                    'settlement_code_expires_at' => null,
                    'operator_code_issued_at'    => null,
                    'operator_code_issued_by'    => null,
                ]);
            });
    }

    public function canAcceptTrips(DriverProfile $profile): bool
    {
        if (! $profile->isOperational()) {
            return false;
        }

        $this->enforceDeadlines();

        $wallet = $this->walletFor($profile);
        $this->refreshAcceptBlock($wallet);

        return $this->acceptBlockReason($profile) === null;
    }

    public function acceptBlockReason(DriverProfile $profile): ?string
    {
        if (! $profile->isOperational()) {
            return 'Tài khoản chưa hoạt động hoặc đang bị khóa.';
        }

        $wallet = $this->walletFor($profile);

        if ($wallet->pendingSettlements()->where('status', 'pending_settle')->exists()) {
            return 'Có chuyến đã hoàn thành — cần chuyển phí nền tảng và nhận mã kết chuyến từ quản lý.';
        }

        if ($wallet->pendingSettlements()->where('status', 'pending_driver_code')->exists()) {
            return 'Có chuyến chờ nhập mã kết chuyến — hoàn tất trước khi nhận cuốc mới.';
        }

        if ($wallet->wallet_gate_enabled && $wallet->balance <= DriverWalletConfig::MIN_BALANCE) {
            return 'Đã có chuyến doanh thu ≥ 500k — từ chuyến tiếp theo cần nạp ví trên '
                . number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') . ' đ.';
        }

        return null;
    }

    public function needsTopUpNotice(DriverProfile $profile): bool
    {
        return $this->shouldShowTopUpBanner($profile);
    }

    /** Banner nạp ví — ẩn khi chưa từng có chuyến ≥500k, đã nạp đủ, hoặc chưa bật cổng ví. */
    public function shouldShowTopUpBanner(DriverProfile $profile): bool
    {
        $wallet = $this->walletFor($profile);

        if (! $wallet->wallet_gate_enabled) {
            return false;
        }

        if ($wallet->balance > DriverWalletConfig::MIN_BALANCE) {
            return false;
        }

        return true;
    }

    public function settlementBlockReason(DriverProfile $profile): ?string
    {
        if (! $profile->isOperational()) {
            return null;
        }

        $wallet = $this->walletFor($profile);

        if ($wallet->pendingSettlements()->where('status', 'pending_settle')->exists()) {
            return 'Có chuyến đã hoàn thành — cần chuyển phí nền tảng và nhận mã kết chuyến từ quản lý.';
        }

        if ($wallet->pendingSettlements()->where('status', 'pending_driver_code')->exists()) {
            return 'Có chuyến chờ nhập mã kết chuyến — hoàn tất trước khi nhận cuốc mới.';
        }

        return null;
    }

    public function driverRevenueBetween(DriverProfile $profile, \Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        return (float) Schedule::query()
            ->with('bookings')
            ->where('driver_id', $profile->user_id)
            ->whereNot('status', 'cancelled')
            ->whereBetween('departure_time', [$start, $end])
            ->get()
            ->sum(fn (Schedule $schedule) => (float) $schedule->tripRevenueTotal());
    }

    public function driverRevenueStats(DriverProfile $profile): array
    {
        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
        $weekEnd = now()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();

        return [
            'day'   => $this->driverRevenueBetween($profile, now()->startOfDay(), now()->endOfDay()),
            'week'  => $this->driverRevenueBetween($profile, $weekStart, $weekEnd),
        ];
    }

    /** @return Collection<int, DriverTripSettlement> */
    public function settlementsAwaitingCodeForOperator(int $operatorId): Collection
    {
        return DriverTripSettlement::query()
            ->with(['schedule.route', 'schedule.bookings', 'booking.schedule.route', 'wallet.driverProfile.user'])
            ->where('status', 'pending_settle')
            ->whereHas('wallet.driverProfile', fn ($q) => $q->where('operator_id', $operatorId))
            ->latest()
            ->get();
    }

    /** @return Collection<int, DriverTripSettlement> */
    public function codesAwaitingDriverForOperator(int $operatorId): Collection
    {
        return DriverTripSettlement::query()
            ->with(['schedule.route', 'schedule.bookings', 'booking.schedule.route', 'wallet.driverProfile.user'])
            ->where('status', 'pending_driver_code')
            ->whereHas('wallet.driverProfile', fn ($q) => $q->where('operator_id', $operatorId))
            ->latest()
            ->get();
    }

    /** @return Collection<int, DriverWalletTransaction> */
    public function pendingDepositsForOperator(int $operatorId): Collection
    {
        return DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('status', 'pending')
            ->whereHas('wallet.driverProfile', fn ($q) => $q->where('operator_id', $operatorId))
            ->latest()
            ->get();
    }

    /** @return Collection<int, DriverWalletTransaction> */
    public function pendingDepositsForDriver(DriverProfile $profile): Collection
    {
        $wallet = $this->walletFor($profile);

        return DriverWalletTransaction::query()
            ->where('driver_wallet_id', $wallet->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    /** Tài xế xác nhận đã chuyển phí nền tảng (chiết khấu / kết chuyến). */
    public function confirmSettlementTransfer(DriverTripSettlement $settlement, string $transferRef): void
    {
        if ($settlement->status !== 'pending_settle') {
            throw new InvalidArgumentException('Chuyến này không còn ở bước chuyển phí.');
        }

        if ($settlement->transfer_ref) {
            throw new InvalidArgumentException('Đã xác nhận chuyển phí cho chuyến này.');
        }

        $transferRef = trim($transferRef);
        if ($transferRef === '') {
            throw new InvalidArgumentException('Vui lòng nhập mã tham chiếu chuyển khoản.');
        }

        $settlement->update(['transfer_ref' => $transferRef]);
    }

    private function resolveCategory(DriverWallet $wallet, int $revenue): string
    {
        if ($revenue < DriverWalletConfig::REVENUE_THRESHOLD) {
            return 'under_threshold';
        }

        $hadPrior500kTrip = $wallet->settlements()
            ->where('status', 'completed')
            ->where('revenue_amount', '>=', DriverWalletConfig::REVENUE_THRESHOLD)
            ->exists();

        if (! $wallet->wallet_gate_enabled && ! $hadPrior500kTrip) {
            return 'first_over_threshold';
        }

        return 'over_threshold';
    }

    private function finalizeSettlement(DriverTripSettlement $settlement): void
    {
        $wallet = $settlement->wallet()->lockForUpdate()->firstOrFail();

        $wallet->update([
            'cumulative_revenue'            => $wallet->cumulative_revenue + $settlement->revenue_amount,
            'completed_settlements_count'   => $wallet->completed_settlements_count + 1,
        ]);

        $wallet = $wallet->fresh();
        $this->refreshWalletGate($wallet, $settlement);
        $this->refreshAcceptBlock($wallet);
    }

    private function refreshWalletGate(DriverWallet $wallet, ?DriverTripSettlement $settlement = null): void
    {
        if ($wallet->wallet_gate_enabled) {
            return;
        }

        if ($settlement && $settlement->revenue_amount >= DriverWalletConfig::REVENUE_THRESHOLD) {
            $wallet->update(['wallet_gate_enabled' => true]);
        }
    }

    private function refreshAcceptBlock(DriverWallet $wallet): void
    {
        $wallet->refresh();

        if ($wallet->pendingSettlements()->whereIn('status', ['pending_settle', 'pending_driver_code'])->exists()) {
            return;
        }

        if ($wallet->wallet_gate_enabled && $wallet->balance <= DriverWalletConfig::MIN_BALANCE) {
            $wallet->update([
                'accept_trips_blocked_at'   => now(),
                'accept_trips_block_reason'   => 'low_balance',
            ]);

            return;
        }

        $wallet->update([
            'accept_trips_blocked_at'   => null,
            'accept_trips_block_reason' => null,
        ]);
    }

    private function generateSettlementCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (DriverTripSettlement::query()
            ->where('settlement_code', $code)
            ->where('settlement_code_expires_at', '>', now())
            ->exists());

        return $code;
    }
}
