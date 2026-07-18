<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuthVerificationCode;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\AuthVerificationService;
use App\Services\CustomerAccountService;
use App\Services\DriverAvailabilityService;
use App\Services\UserInboxService;
use App\Support\AuthMessages;
use App\Support\RoleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterOtpController extends Controller
{
    public function __construct(
        private readonly AuthVerificationService $verification,
        private readonly CustomerAccountService $accounts,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly UserInboxService $inbox,
    ) {
    }

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.register-otp', [
            'phone' => $user->phone,
            'role'  => $user->role,
        ]);
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], AuthMessages::code());

        try {
            $this->verification->verify(
                (string) $user->phone,
                AuthVerificationCode::PURPOSE_REGISTER_OTP,
                $validated['code'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->forget(['pending_register_otp.user_id']);

        Auth::login($user, false);
        $request->session()->regenerate();

        $this->inbox->notifyRegistrationSuccess($user);

        if ($user->role === 'customer') {
            $request->session()->put('customer_biometric_verified', true);
            $this->accounts->linkExistingBookings($user);

            return redirect()->route('home')
                ->with('success', 'Xác minh thành công. Hồ sơ đang chờ duyệt CCCD — bạn có thể xem trang chủ, đặt xe sau khi được duyệt.');
        }

        if ($user->role === 'driver') {
            $profile = DriverProfile::query()->where('user_id', $user->id)->first();
            if ($profile) {
                try {
                    $this->driverAvailability->resetForWebLogin($profile);
                } catch (\Throwable) {
                    // không chặn đăng nhập
                }
            }

            return redirect(RoleDashboard::forUser($user, $request))
                ->with('success', 'Xác minh thành công. Hồ sơ tài xế đang chờ duyệt — bạn có thể xem app, nhận chuyến sau khi được duyệt.');
        }

        return redirect(RoleDashboard::forUser($user, $request));
    }

    public function resend(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $this->verification->resend(
            (string) $user->phone,
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            AuthVerificationService::REGISTER_TTL_MINUTES,
            $user,
        );

        return back()->with('success', 'Đã gửi lại mã OTP. Liên hệ admin để nhận mã (hiệu lực 5 phút).');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('pending_register_otp.user_id');
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }
}
