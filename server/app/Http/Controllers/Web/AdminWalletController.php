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
 * Duyệt nạp ví TX + KH (một trang admin).
 */
class AdminWalletController extends Controller
{
    public function __construct(
        private readonly DriverWalletService $driverWallet,
        private readonly CustomerWalletService $customerWallet,
    ) {
    }

    public function index(Request $request)
    {
        $this->driverWallet->enforceDeadlines();

        $driverPending = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (DriverWalletTransaction $tx) => $this->mapPendingDriver($tx));

        $customerPending = CustomerWalletTransaction::query()
            ->with(['wallet.user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (CustomerWalletTransaction $tx) => $this->mapPendingCustomer($tx));

        $depositsPendingAll = $driverPending
            ->concat($customerPending)
            ->sortByDesc(fn (array $row) => $row['created_at']?->getTimestamp() ?? 0)
            ->values();

        $driverHistory = DriverWalletTransaction::query()
            ->with(['wallet.driverProfile.user'])
            ->where('type', 'deposit')
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (DriverWalletTransaction $tx) => $this->mapHistoryDriver($tx));

        $customerHistory = CustomerWalletTransaction::query()
            ->with(['wallet.user'])
            ->where('type', 'deposit')
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (CustomerWalletTransaction $tx) => $this->mapHistoryCustomer($tx));

        $walletHistoryAll = $driverHistory
            ->concat($customerHistory)
            ->sortByDesc(fn (array $row) => $row['at']?->getTimestamp() ?? 0)
            ->take(80)
            ->values();

        $depositsPending = PageList::paginateCollection($depositsPendingAll, $request, 'deposit_page');
        $walletHistory = PageList::paginateCollection($walletHistoryAll, $request, 'history_page');

        $counts = [
            'deposits' => $depositsPendingAll->count(),
            'total'    => $depositsPendingAll->count(),
        ];

        return view('admin.wallet-deposits', compact(
            'depositsPending',
            'walletHistory',
            'counts',
        ));
    }

    /** @deprecated Dùng index — giữ tên route admin.driverWallet. */
    public function driverWallet(Request $request)
    {
        return $this->index($request);
    }

    public function customerWallet()
    {
        return redirect()->route('admin.walletDeposits');
    }

    /** @return array<string, mixed> */
    private function mapPendingDriver(DriverWalletTransaction $tx): array
    {
        $user = $tx->wallet?->driverProfile?->user;

        return [
            'bulk_key'        => 'driver:'.$tx->id,
            'role'            => 'driver',
            'role_label'      => 'Tài xế',
            'phone'           => $user?->phone ?: '—',
            'display_name'    => $user?->preferredDisplayName() ?: ($user?->name ?: '—'),
            'amount'          => (int) $tx->amount,
            'reference'       => $tx->depositReference(),
            'proof_url'       => $tx->proofImageUrl(),
            'created_at'      => $tx->created_at,
            'approve_url'     => route('admin.walletTransactions.approve', $tx),
            'reject_url'      => route('admin.walletTransactions.reject', $tx),
        ];
    }

    /** @return array<string, mixed> */
    private function mapPendingCustomer(CustomerWalletTransaction $tx): array
    {
        $user = $tx->wallet?->user;

        return [
            'bulk_key'        => 'customer:'.$tx->id,
            'role'            => 'customer',
            'role_label'      => 'Khách',
            'phone'           => $user?->phone ?: '—',
            'display_name'    => $user?->preferredDisplayName() ?: ($user?->name ?: '—'),
            'amount'          => (int) $tx->amount,
            'reference'       => $tx->depositReference(),
            'proof_url'       => $tx->proofImageUrl(),
            'created_at'      => $tx->created_at,
            'approve_url'     => route('admin.customerWallet.approve', $tx),
            'reject_url'      => route('admin.customerWallet.reject', $tx),
        ];
    }

    /** @return array<string, mixed> */
    private function mapHistoryDriver(DriverWalletTransaction $tx): array
    {
        $user = $tx->wallet?->driverProfile?->user;

        return [
            'kind'            => 'deposit',
            'role_label'      => 'Tài xế',
            'phone'           => $user?->phone ?: '—',
            'display_name'    => $user?->preferredDisplayName() ?: ($user?->name ?: '—'),
            'amount'          => (int) $tx->amount,
            'at'              => $tx->created_at,
            'label'           => DriverWalletTransaction::historyLabelFor($tx->status),
            'meta'            => match ($tx->status) {
                'approved' => 'Duyệt '.($tx->approved_at?->format('d/m/Y H:i') ?? '—'),
                'rejected' => 'Từ chối '.($tx->approved_at?->format('d/m/Y H:i') ?? '—'),
                default    => 'Gửi '.$tx->created_at->format('d/m/Y H:i'),
            },
            'status'          => $tx->status,
            'proof_image_url' => $tx->proofImageUrl(),
            'reference'       => $tx->depositReference(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapHistoryCustomer(CustomerWalletTransaction $tx): array
    {
        $user = $tx->wallet?->user;

        return [
            'kind'            => 'deposit',
            'role_label'      => 'Khách',
            'phone'           => $user?->phone ?: '—',
            'display_name'    => $user?->preferredDisplayName() ?: ($user?->name ?: '—'),
            'amount'          => (int) $tx->amount,
            'at'              => $tx->created_at,
            'label'           => 'Nạp ví',
            'meta'            => match ($tx->status) {
                'approved' => 'Duyệt '.($tx->approved_at?->format('d/m/Y H:i') ?? '—'),
                'rejected' => 'Từ chối '.($tx->approved_at?->format('d/m/Y H:i') ?? '—'),
                default    => 'Gửi '.$tx->created_at->format('d/m/Y H:i'),
            },
            'status'          => $tx->status,
            'proof_image_url' => $tx->proofImageUrl(),
            'reference'       => $tx->depositReference(),
        ];
    }

    public function approveDeposit(DriverWalletTransaction $transaction)
    {
        try {
            $this->driverWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.walletDeposits')
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
            ->route('admin.walletDeposits')
            ->with('success', 'Đã từ chối yêu cầu nạp ví.');
    }

    public function approveCustomerDeposit(CustomerWalletTransaction $transaction)
    {
        try {
            $this->customerWallet->approveDeposit($transaction, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.walletDeposits')
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
            ->route('admin.walletDeposits')
            ->with('success', 'Đã từ chối yêu cầu nạp ví khách.');
    }

    public function approveDepositsBulk(ApproveDepositsBulkRequest $request)
    {
        $validated = $request->validated();
        $actorId = Auth::id();
        $approved = 0;
        $skipped = 0;

        foreach ($validated['items'] as $item) {
            if (! is_string($item) || ! str_contains($item, ':')) {
                $skipped++;
                continue;
            }

            [$type, $idRaw] = explode(':', $item, 2);
            $id = (int) $idRaw;
            if ($id < 1) {
                $skipped++;
                continue;
            }

            try {
                if ($type === 'driver') {
                    $tx = DriverWalletTransaction::query()->find($id);
                    if (! $tx || $tx->status !== 'pending') {
                        $skipped++;
                        continue;
                    }
                    $this->driverWallet->approveDeposit($tx, $actorId);
                    $approved++;
                } elseif ($type === 'customer') {
                    $tx = CustomerWalletTransaction::query()->find($id);
                    if (! $tx || $tx->status !== 'pending') {
                        $skipped++;
                        continue;
                    }
                    $this->customerWallet->approveDeposit($tx, $actorId);
                    $approved++;
                } else {
                    $skipped++;
                }
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }

        if ($approved < 1) {
            return back()->withErrors(['wallet' => 'Không có đơn nạp hợp lệ để duyệt.']);
        }

        $message = "Đã duyệt {$approved} đơn nạp và cộng ví.";
        if ($skipped > 0) {
            $message .= " ({$skipped} đơn bỏ qua — đã xử lý hoặc không hợp lệ.)";
        }

        return redirect()
            ->route('admin.walletDeposits')
            ->with('success', $message);
    }
}
