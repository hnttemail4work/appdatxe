<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CustomerWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CustomerWalletController extends Controller
{
    public function __construct(private readonly CustomerWalletService $wallets)
    {
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:' . CustomerWalletService::MIN_DEPOSIT],
            'proof_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ], [
            'amount.required'      => 'Vui lòng nhập số tiền nạp.',
            'amount.min'           => 'Số tiền nạp tối thiểu ' . number_format(CustomerWalletService::MIN_DEPOSIT, 0, ',', '.') . ' đ.',
            'proof_image.required' => 'Vui lòng đính kèm ảnh chụp chuyển khoản.',
            'proof_image.image'    => 'Ảnh chụp chuyển khoản phải là file ảnh.',
            'proof_image.mimes'    => 'Ảnh chụp chuyển khoản phải là JPG, PNG, WebP hoặc GIF.',
            'proof_image.max'      => 'Ảnh chụp chuyển khoản tối đa 5MB.',
        ]);

        try {
            $this->wallets->requestDeposit(
                Auth::user(),
                (int) $request->input('amount'),
                $request->file('proof_image'),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('customer.account', ['tab' => 'wallet'])
                ->withErrors(['wallet' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('customer.account', ['tab' => 'wallet'])
            ->with('success', 'Đã gửi yêu cầu nạp tiền. Admin sẽ duyệt trước khi cộng ví.');
    }
}
