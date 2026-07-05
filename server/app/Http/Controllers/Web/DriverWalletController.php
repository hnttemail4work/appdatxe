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
        $wantsJson = $request->expectsJson() || $request->ajax();

        $validator = Validator::make($request->all(), [
            'amount'      => ['required', 'numeric', 'min:' . DriverWalletConfig::MIN_DEPOSIT],
            'proof_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ], [
            'amount.required'      => 'Vui lòng nhập số tiền nạp.',
            'amount.min'           => 'Số tiền nạp tối thiểu ' . DriverWalletConfig::minDepositFormatted() . '.',
            'proof_image.image'    => 'Ảnh chụp chuyển khoản phải là file ảnh.',
            'proof_image.mimes'    => 'Ảnh chụp chuyển khoản phải là JPG, PNG, WebP hoặc GIF.',
            'proof_image.max'      => 'Ảnh chụp chuyển khoản tối đa 5MB.',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return $depositRedirect()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

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
