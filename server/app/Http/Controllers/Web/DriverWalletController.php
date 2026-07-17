<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\DriverWalletDepositRequest;
use App\Models\DriverProfile;
use App\Services\DriverWalletService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class DriverWalletController extends Controller
{
    public function __construct(private readonly DriverWalletService $wallets)
    {
    }

    public function deposit(DriverWalletDepositRequest $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $depositRedirect = fn () => redirect()->route('driver.dashboard', ['tab' => 'deposit']);
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validated = $request->validated();

        try {
            $transaction = $this->wallets->requestDeposit(
                $profile,
                (int) $validated['amount'],
                $request->file('proof_image'),
            );
        } catch (InvalidArgumentException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return $depositRedirect()
                ->withErrors(['wallet' => $e->getMessage()])
                ->withInput();
        }

        $message = 'Đã gửi yêu cầu nạp tiền.';

        if ($wantsJson) {
            session()->flash('success', $message);

            return response()->json([
                'ok'       => true,
                'message'  => $message,
                'redirect' => route('driver.dashboard', ['tab' => 'deposit']),
            ]);
        }

        return $depositRedirect()->with('success', $message);
    }
}
