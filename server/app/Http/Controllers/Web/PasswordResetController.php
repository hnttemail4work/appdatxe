<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuthVerificationCode;
use App\Models\User;
use App\Services\AuthVerificationService;
use App\Support\AuthAudience;
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

    public function showRequestForm(Request $request)
    {
        if (AuthAudience::isDriver($request)) {
            AuthAudience::rememberDriver($request, true);
        }

        return view('auth.password-reset-request', [
            'forDriver' => AuthAudience::isDriver($request),
            'loginUrl'  => AuthAudience::loginUrl($request),
        ]);
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
        $user = AuthIdentifier::findUserByPhone($phone);
        if ($user) {
            AuthAudience::rememberFromUser($request, $user);
        } elseif (AuthAudience::isDriver($request)) {
            AuthAudience::rememberDriver($request, true);
        }

        $request->session()->put('password_reset.phone', $phone);

        return redirect()
            ->route('password.reset.code')
            ->with('success', 'Đã gửi yêu cầu. Admin sẽ cấp mã 6 số (hiệu lực '.\App\Support\AuthOtp::ttlLabel().'). Nhập mã khi nhận được.');
    }

    public function showCodeForm(Request $request)
    {
        $phone = (string) $request->session()->get('password_reset.phone', '');
        if ($phone === '') {
            return redirect()->route('password.reset.request');
        }

        return view('auth.password-reset-code', [
            'phone'     => $phone,
            'forDriver' => AuthAudience::isDriver($request),
            'loginUrl'  => AuthAudience::loginUrl($request),
        ]);
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

        if ($record->user_id) {
            $user = User::query()->find($record->user_id);
            if ($user) {
                AuthAudience::rememberFromUser($request, $user);
            }
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

        return view('auth.password-reset-pin', [
            'forDriver' => AuthAudience::isDriver($request),
            'loginUrl'  => AuthAudience::loginUrl($request),
        ]);
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

        AuthAudience::rememberFromUser($request, $user);

        $request->session()->forget([
            'password_reset.phone',
            'password_reset.verified_user_id',
            'password_reset.verified_at',
        ]);

        return redirect()
            ->to(AuthAudience::loginUrl($request))
            ->with('success', 'Đã đặt PIN mới. Đăng nhập bằng số điện thoại và PIN.');
    }

    private function resolvedResetUser(Request $request): ?User
    {
        $userId = $request->session()->get('password_reset.verified_user_id');
        $verifiedAt = (int) $request->session()->get('password_reset.verified_at', 0);

        if (! $userId || $verifiedAt < now()->subMinutes(30)->timestamp) {
            return null;
        }

        $user = User::query()->find($userId);
        if ($user) {
            AuthAudience::rememberFromUser($request, $user);
        }

        return $user;
    }
}
