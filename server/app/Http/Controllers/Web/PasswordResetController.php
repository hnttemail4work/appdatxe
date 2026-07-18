<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Services\AuthVerificationService;
use App\Support\AuthIdentifier;
use App\Support\AuthMessages;
use App\Support\AuthPhone;
use App\Support\PinPassword;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuthVerificationService $verification,
    ) {
    }

    public function showRequestForm()
    {
        return view('auth.password-reset-request');
    }

    public function requestReset(Request $request)
    {
        $validated = $request->validate([
            'phone' => AuthPhone::rules(),
        ], AuthMessages::phone());

        try {
            $this->verification->requestPasswordReset($validated['phone']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $phone = AuthIdentifier::normalizePhone($validated['phone']);
        $request->session()->put('password_reset.phone', $phone);

        return redirect()
            ->route('password.reset.code')
            ->with('success', 'Đã gửi yêu cầu. Admin sẽ cấp mã 6 số (hiệu lực 30 phút). Nhập mã khi nhận được.');
    }

    public function showCodeForm(Request $request)
    {
        $phone = (string) $request->session()->get('password_reset.phone', '');
        if ($phone === '') {
            return redirect()->route('password.reset.request');
        }

        return view('auth.password-reset-code', ['phone' => $phone]);
    }

    public function verifyCode(Request $request)
    {
        $phone = (string) $request->session()->get('password_reset.phone', '');
        if ($phone === '') {
            return redirect()->route('password.reset.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], AuthMessages::code());

        try {
            $record = $this->verification->verify(
                $phone,
                AuthVerificationCode::PURPOSE_PASSWORD_RESET,
                $validated['code'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->put('password_reset.verified_user_id', $record->user_id);
        $request->session()->put('password_reset.verified_at', now()->timestamp);

        return redirect()->route('password.reset.pin');
    }

    public function showNewPinForm(Request $request)
    {
        if (! $this->resolvedResetUser($request)) {
            return redirect()->route('password.reset.request')
                ->withErrors(['phone' => 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng thử lại.']);
        }

        return view('auth.password-reset-pin');
    }

    public function storeNewPin(Request $request)
    {
        $user = $this->resolvedResetUser($request);
        if (! $user) {
            return redirect()->route('password.reset.request')
                ->withErrors(['phone' => 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng thử lại.']);
        }

        $validated = $request->validate([
            'password' => PinPassword::rules(confirmed: true),
        ], AuthMessages::pin());

        $pin = PinPassword::assertValid($validated['password']);

        $user->forceFill([
            'password'             => $pin,
            'must_change_password' => false,
            'login_fail_count'     => 0,
            'login_locked_until'   => null,
        ])->save();

        $request->session()->forget([
            'password_reset.phone',
            'password_reset.verified_user_id',
            'password_reset.verified_at',
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Đã đặt PIN mới. Đăng nhập bằng số điện thoại và PIN.');
    }

    private function resolvedResetUser(Request $request): ?User
    {
        $userId = $request->session()->get('password_reset.verified_user_id');
        $verifiedAt = (int) $request->session()->get('password_reset.verified_at', 0);

        if (! $userId || $verifiedAt < now()->subMinutes(30)->timestamp) {
            return null;
        }

        return User::query()->find($userId);
    }
}
