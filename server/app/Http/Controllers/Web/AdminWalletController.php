<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveDepositsBulkRequest;
use App\Models\CustomerWalletTransaction;
use App\Models\DriverWalletTransaction;
use App\Services\CustomerWalletService;
use App\Services\DriverWalletService;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Nhóm "ví tài xế (duyệt nạp tiền)" — tách ra từ AdminController (Fat Controller).
 */
class AdminWalletController extends Controller
{
    public function __construct(
        private readonly DriverWalletService $driverWallet,
        private readonly CustomerWalletService $customerWallet,
    ) {
    }

    public function driverWallet(Request $request)
    {
        $this->driverWallet->enforceDeadlines();

        $depositsPendingAll = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $walletHistoryAll = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (DriverWalletTransaction $transaction) => [
                'kind'            => 'deposit',
                'amount'          => (int) $transaction->amount,
                'at'              => $transaction->created_at,
                'label'           => DriverWalletTransaction::historyLabelFor($transaction->status),
                'meta'            => match ($transaction->status) {
                    'approved' => 'Duyệt ' . ($transaction->approved_at?->format('d/m/Y H:i') ?? '—'),
                    'rejected' => 'Từ chối ' . ($transaction->approved_at?->format('d/m/Y H:i') ?? '—'),
                    default    => 'Gửi ' . $transaction->created_at->format('d/m/Y H:i'),
                },
                'status'          => $transaction->status,
                'driver_name'     => $transaction->wallet->driverProfile->user->name ?? '—',
                'driver_code'     => $transaction->wallet->driverProfile->driver_code ?? null,
                'proof_image_url' => $transaction->proofImageUrl(),
                'reference'       => $transaction->depositReference(),
            ]);

        $depositsPending = PageList::paginateCollection($depositsPendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        $counts = [
            'deposits'    => $depositsPendingAll->count(),
            'settlements' => 0,
            'total'       => $depositsPendingAll->count(),
        ];

        return view('admin.driver-wallet', compact(
            'depositsPending',
            'walletHistory',
            'counts',
        ));
    }

    public function approveDeposit(DriverWalletTransaction $transaction)
    {
        try {
            $this->driverWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', 'Đã cộng tiền vào ví tài xế.');
    }

    public function rejectDeposit(DriverWalletTransaction $transaction)
    {
        try {
            $this->driverWallet->rejectDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', 'Đã từ chối yêu cầu nạp ví.');
    }

    public function approveDepositsBulk(ApproveDepositsBulkRequest $request)
    {
        $validated = $request->validated();

        $result = $this->driverWallet->approveDepositsBulk(
            array_map('intval', $validated['transaction_ids']),
            Auth::id(),
        );

        if ($result['approved'] < 1) {
            return back()->withErrors(['wallet' => 'Không có đơn nạp hợp lệ để duyệt.']);
        }

        $message = "Đã duyệt {$result['approved']} đơn nạp và cộng ví.";
        if ($result['skipped'] > 0) {
            $message .= " ({$result['skipped']} đơn bỏ qua — đã xử lý hoặc không hợp lệ.)";
        }

        return redirect()
            ->route('admin.driverWallet')
            ->with('success', $message);
    }

    public function customerWallet(Request $request)
    {
        $pendingAll = CustomerWalletTransaction::query()
            ->with(['wallet.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $historyAll = CustomerWalletTransaction::query()
            ->with(['wallet.user'])
            ->where('type', 'deposit')
            ->latest()
            ->limit(80)
            ->get();

        $depositsPending = PageList::paginateCollection($pendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($historyAll, $request, 'history_page');

        return view('admin.customer-wallet', [
            'depositsPending' => $depositsPending,
            'walletHistory' => $walletHistory,
            'counts' => [
                'deposits' => $pendingAll->count(),
                'total' => $pendingAll->count(),
            ],
        ]);
    }

    public function approveCustomerDeposit(CustomerWalletTransaction $transaction)
    {
        try {
            $this->customerWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.customerWallet')
            ->with('success', 'Đã cộng tiền vào ví khách.');
    }

    public function rejectCustomerDeposit(CustomerWalletTransaction $transaction)
    {
        try {
            $this->customerWallet->rejectDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.customerWallet')
            ->with('success', 'Đã từ chối yêu cầu nạp ví khách.');
    }
}
