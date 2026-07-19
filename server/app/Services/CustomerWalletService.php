<?php

namespace App\Services;

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
    }
}
