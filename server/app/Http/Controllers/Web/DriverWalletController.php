<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\DriverTripSettlement;
use App\Services\DriverWalletService;
use App\Support\DriverWalletConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
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
        $depositRedirect = fn () => redirect()->route('driver.dashboard', ['tab' => 'deposit']);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . DriverWalletConfig::MIN_BALANCE],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền nạp.',
            'amount.min'      => 'Số tiền nạp tối thiểu ' . number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') . ' đ.',
        ]);

        if ($validator->fails()) {
            return $depositRedirect()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            $this->wallets->requestDeposit($profile, (int) $validated['amount']);
        } catch (InvalidArgumentException $e) {
            return $depositRedirect()
                ->withErrors(['wallet' => $e->getMessage()])
                ->withInput();
        }

        return $depositRedirect()
            ->with('success', 'Đã gửi yêu cầu nạp tiền — chờ quản lý cộng vào ví.');
    }

    public function confirmSettlementTransfer(Request $request, DriverTripSettlement $settlement)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $wallet = $this->wallets->walletFor($profile);

        if ($settlement->driver_wallet_id !== $wallet->id) {
            abort(403);
        }

        $transferRef = null;
        if (! $settlement->isUnderThreshold()) {
            $validated = $request->validate([
                'transfer_ref' => ['required', 'string', 'max:100'],
            ]);
            $transferRef = $validated['transfer_ref'];
        }

        try {
            $this->wallets->confirmSettlementTransfer($settlement, $transferRef);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()])->withInput();
        }

        $message = $settlement->isUnderThreshold()
            ? 'Đã xác nhận chuyển phí — chờ quản lý xác nhận.'
            : 'Xác nhận chuyển phí thành công — chờ quản lý cấp mã kết chuyến.';

        return redirect()
            ->route('driver.dashboard', ['tab' => 'trips'])
            ->with('success', $message);
    }
}
