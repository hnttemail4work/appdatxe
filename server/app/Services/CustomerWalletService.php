<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerWalletService
{
    public const MIN_DEPOSIT = 100_000;

    public function walletFor(User $user): CustomerWallet
    {
        return CustomerWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0],
        );
    }

    public function balanceFor(User $user): int
    {
        return (int) $this->walletFor($user)->balance;
    }

    /** Kiểm tra số dư trước khi đặt chuyến (trừ ví khi hoàn thành). */
    public function assertCanCoverTrip(User $user, int $amount): void
    {
        if ($user->role !== 'customer') {
            throw new InvalidArgumentException('Chỉ tài khoản khách mới thanh toán bằng ví.');
        }

        if ($amount <= 0) {
            return;
        }

        $balance = $this->balanceFor($user);
        if ($balance < $amount) {
            throw new InvalidArgumentException(
                'Số dư ví không đủ (cần '.number_format($amount, 0, ',', '.').' đ, hiện có '
                .number_format($balance, 0, ',', '.').' đ).'
            );
        }
    }

    /** Trừ ví sau khi chuyến hoàn thành — idempotent theo booking_id. */
    public function chargeForCompletedBooking(Booking $booking): void
    {
        if (($booking->payment_method ?? '') !== 'wallet') {
            return;
        }

        if ($booking->payment_status === 'paid') {
            return;
        }

        $amount = $booking->tripRevenueAmount();
        $customerId = (int) ($booking->customer_id ?? 0);
        if ($customerId <= 0) {
            return;
        }

        $user = User::query()->find($customerId);
        if (! $user || $user->role !== 'customer') {
            return;
        }

        if ($amount <= 0) {
            $booking->update(['payment_status' => 'paid']);

            return;
        }

        DB::transaction(function () use ($booking, $user, $amount): void {
            $existing = CustomerWalletTransaction::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'trip_fare')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($booking->payment_status !== 'paid') {
                    $booking->update(['payment_status' => 'paid']);
                }

                return;
            }

            $wallet = CustomerWallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = $this->walletFor($user);
                $wallet = CustomerWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            }

            if ((int) $wallet->balance < $amount) {
                return;
            }

            $wallet->update([
                'balance' => (int) $wallet->balance - $amount,
            ]);

            CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $wallet->id,
                'booking_id'         => $booking->id,
                'type'               => 'trip_fare',
                'amount'             => $amount,
                'status'             => 'approved',
                'approved_at'        => now(),
                'notes'              => 'Trừ ví chuyến '.$booking->booking_reference,
            ]);

            $booking->update(['payment_status' => 'paid']);
        });
    }

    public function requestDeposit(User $user, int $amount, ?UploadedFile $proofImage = null): CustomerWalletTransaction
    {
        if ($amount < self::MIN_DEPOSIT) {
            throw new InvalidArgumentException('Số tiền nạp tối thiểu ' . number_format(self::MIN_DEPOSIT, 0, ',', '.') . ' đ.');
        }

        $wallet = $this->walletFor($user);

        $pendingCount = $wallet->transactions()
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= 1) {
            throw new InvalidArgumentException('Đang có yêu cầu nạp chờ duyệt — chờ admin xác nhận trước khi gửi thêm.');
        }

        $transaction = CustomerWalletTransaction::query()->create([
            'customer_wallet_id' => $wallet->id,
            'type'               => 'deposit',
            'amount'             => $amount,
            'status'             => 'pending',
        ]);

        if ($proofImage) {
            try {
                $path = app(ImageCompressService::class)->storeOptimized(
                    $proofImage,
                    'customer-wallet/deposit-proofs/' . $wallet->id,
                    'deposit-' . $transaction->id,
                    1280,
                );
            } catch (InvalidArgumentException $e) {
                $transaction->delete();
                throw $e;
            }
            $transaction->update(['proof_image_path' => $path]);
        }

        $fresh = $transaction->fresh();
        app(AdminOperatorAlertService::class)->recordCustomerDepositPending($fresh);

        return $fresh;
    }

    public function approveDeposit(CustomerWalletTransaction $transaction, int $actorId): void
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            throw new InvalidArgumentException('Giao dịch không còn chờ duyệt.');
        }

        DB::transaction(function () use ($transaction, $actorId): void {
            $wallet = CustomerWallet::query()->lockForUpdate()->findOrFail($transaction->customer_wallet_id);
            $transaction->update([
                'status'      => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);
            $wallet->update([
                'balance' => $wallet->balance + $transaction->amount,
            ]);
        });

        $this->notifyDepositInbox($transaction->fresh(), approved: true);
    }

    public function rejectDeposit(CustomerWalletTransaction $transaction, int $actorId): void
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            throw new InvalidArgumentException('Giao dịch không còn chờ duyệt.');
        }

        $transaction->update([
            'status'      => 'rejected',
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        $this->notifyDepositInbox($transaction->fresh(), approved: false);
    }

    private function notifyDepositInbox(CustomerWalletTransaction $transaction, bool $approved): void
    {
        $transaction->loadMissing('wallet.user');
        $user = $transaction->wallet?->user;
        if (! $user instanceof User) {
            return;
        }

        app(UserInboxService::class)->notifyDepositResult(
            $user,
            (int) $transaction->amount,
            $approved,
        );
    }
}
