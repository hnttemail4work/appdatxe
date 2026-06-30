<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
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

    public function deposit(Request $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $depositRedirect = fn () => redirect()->route('driver.dashboard', ['tab' => 'deposit']);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . DriverWalletConfig::MIN_DEPOSIT],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền nạp.',
            'amount.min'      => 'Số tiền nạp tối thiểu ' . DriverWalletConfig::minDepositFormatted() . '.',
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
}
