<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\DriverTripSettlement;
use App\Services\DriverWalletService;
use App\Support\DriverWalletConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class DriverWalletController extends Controller
{
    public function __construct(private readonly DriverWalletService $wallets)
    {
    }

    public function settle(Request $request, DriverTripSettlement $settlement)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $wallet = $this->wallets->walletFor($profile);

        if ($settlement->driver_wallet_id !== $wallet->id) {
            abort(403);
        }

        $validated = $request->validate([
            'settlement_code' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->wallets->settleTrip($settlement, $validated['settlement_code']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', 'Đã kết chuyến thành công.');
    }

    public function deposit(Request $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'amount'       => ['required', 'integer', 'min:' . DriverWalletConfig::MIN_BALANCE],
            'transfer_ref' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->wallets->requestDeposit($profile, (int) $validated['amount'], $validated['transfer_ref']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('driver.dashboard', ['tab' => 'deposit'])
            ->with('success', 'Nạp tiền thành công.');
    }

    public function confirmSettlementTransfer(Request $request, DriverTripSettlement $settlement)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $wallet = $this->wallets->walletFor($profile);

        if ($settlement->driver_wallet_id !== $wallet->id) {
            abort(403);
        }

        $validated = $request->validate([
            'transfer_ref' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->wallets->confirmSettlementTransfer($settlement, $validated['transfer_ref']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', 'Xác nhận chuyển phí thành công — chờ quản lý cấp mã kết chuyến.');
    }
}
