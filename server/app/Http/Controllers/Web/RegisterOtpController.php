<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuthVerificationCode;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\AuthVerificationService;
use App\Services\CustomerAccountService;
use App\Services\DriverAvailabilityService;
use App\Services\RegistrationService;
use App\Services\UserInboxService;
use App\Support\AuthAudience;
use App\Support\AuthMessages;
use App\Support\AuthOtp;
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
        private readonly RegistrationService $registration,
    ) {
    }

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->to(AuthAudience::loginUrl($request));
        }

        AuthAudience::rememberFromUser($request, $user);

        $awaitingApproval = $user->isAwaitingApprovalForRegisterOtp();
        $canEnterOtp = $user->needsPostApprovalRegisterOtp();

        return view('auth.register-otp', [
            'phone'            => $user->phone,
            'role'             => $user->role,
            'forDriver'        => $user->role === 'driver',
            'loginUrl'         => AuthAudience::loginUrl($request),
            'awaitingApproval' => $awaitingApproval,
            'canEnterOtp'      => $canEnterOtp,
        ]);
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->to(AuthAudience::loginUrl($request));
        }

        if ($user->isAwaitingApprovalForRegisterOtp()) {
            return back()->withErrors([
                'code' => AuthMessages::CODE_INVALID,
            ])->withInput();
        }

        if (! $user->needsPostApprovalRegisterOtp()) {
            return redirect()->to(AuthAudience::loginUrl($request));
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], AuthMessages::code());

        $firstOtpCompletion = ! $this->registration->hasCompletedRegisterOtp($user);

        try {
            $this->verification->verify(
                (string) $user->phone,
                AuthVerificationCode::PURPOSE_REGISTER_OTP,
                $validated['code'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($firstOtpCompletion) {
            $user->forceFill(['register_otp_verified_at' => now()])->save();
            $this->inbox->notifyRegisterOtpVerified($user);
        }

        $request->session()->forget(['pending_register_otp.user_id']);

        Auth::login($user, false);
        $request->session()->regenerate();

        if ($user->role === 'customer') {
            $this->accounts->linkExistingBookings($user);

            return redirect()->route('home');
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

            return redirect(RoleDashboard::forUser($user, $request));
        }

        return redirect(RoleDashboard::forUser($user, $request));
    }

    public function resend(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->to(AuthAudience::loginUrl($request));
        }

        if ($user->isAwaitingApprovalForRegisterOtp()) {
            return back()->with('info', AuthOtp::awaitingApprovalOtpNotice($user->isCustomer()));
        }

        if (! $user->needsPostApprovalRegisterOtp()) {
            return redirect()->to(AuthAudience::loginUrl($request));
        }

        $this->verification->resend(
            (string) $user->phone,
            AuthVerificationCode::PURPOSE_REGISTER_OTP,
            AuthOtp::TTL_MINUTES,
            $user,
        );

        return back()->with('success', AuthOtp::resendSuccess());
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('pending_register_otp.user_id');
        if (! $userId) {
            return null;
        }

        $user = User::query()->with('driverProfile')->find($userId);
        if (! $user) {
            $request->session()->forget(['pending_register_otp.user_id']);

            return null;
        }

        AuthAudience::rememberFromUser($request, $user);

        if ($user->isCustomer() && $user->isCustomerApprovalPending() && $user->isCustomerPendingApprovalExpired()) {
            app(\App\Services\PendingApprovalExpiryService::class)->expireCustomer($user);
            $user->refresh();
        }
        if ($user->role === 'driver' && $user->driverProfile?->isPendingApprovalExpired()) {
            app(\App\Services\PendingApprovalExpiryService::class)->expireDriver($user->driverProfile);
            $user->unsetRelation('driverProfile');
            $user->load('driverProfile');
        }
        if ($user->isCustomer() && $user->customerAllowsFreshRegistration()) {
            $request->session()->forget(['pending_register_otp.user_id']);

            return null;
        }
        if ($user->role === 'driver' && $user->driverProfile?->isRejected()) {
            $request->session()->forget(['pending_register_otp.user_id']);

            return null;
        }

        if (! $user->canStayOnRegisterOtpPage()) {
            $request->session()->forget(['pending_register_otp.user_id']);

            return null;
        }

        return $user;
    }
}
