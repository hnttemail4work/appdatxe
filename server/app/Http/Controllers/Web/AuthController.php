<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDriverRequest;
use App\Models\DriverProfile;
use App\Services\AuthLoginGuard;
use App\Services\CustomerAccountService;
use App\Services\DriverAvailabilityService;
use App\Services\RegistrationService;
use App\Support\AuthAudience;
use App\Support\AuthIdentifier;
use App\Support\RoleDashboard;
use App\Support\WebAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly AuthLoginGuard $loginGuard,
        private readonly CustomerAccountService $customerAccounts,
    ) {
    }

    public function showLogin(Request $request)
    {
        $forDriver = $this->isDriverAuthAudience($request);
        AuthAudience::rememberDriver($request, $forDriver);
        $phone = AuthIdentifier::normalizePhone((string) $request->query('phone', ''));

        return view('auth.login', [
            'forDriver'   => $forDriver,
            'loginAction' => route('login'),
            'phone'       => $phone !== '' ? $phone : null,
        ]);
    }

    /** JSON: missing | needs_otp | inactive | active — login PIN + chặn sớm đăng ký. */
    public function checkPhone(Request $request)
    {
        $forDriver = $this->isDriverAuthAudience($request);
        AuthAudience::rememberDriver($request, $forDriver);

        $result = $this->registration->resolvePhoneAuthStatus(
            $request,
            (string) $request->input('phone', ''),
            $forDriver,
        );

        $statusCode = ($result['status'] ?? '') === 'invalid' ? 422 : 200;

        return response()->json($result, $statusCode);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $phone = AuthIdentifier::normalizePhone($validated['phone']);
        $forDriver = $this->isDriverAuthAudience($request);
        AuthAudience::rememberDriver($request, $forDriver);
        $role = $forDriver ? 'driver' : 'customer';
        $registerRoute = $forDriver ? 'driver.register' : 'customer.register';
        $user = AuthIdentifier::findUserByPhoneAndRole($phone, $role);

        if (! $user) {
            return redirect()
                ->route($registerRoute, ['phone' => $phone])
                ->with('info', 'Số điện thoại chưa có tài khoản. Vui lòng đăng ký.');
        }

        if ($user->isCustomer() && $user->isCustomerApprovalPending() && $user->isCustomerPendingApprovalExpired()) {
            app(\App\Services\PendingApprovalExpiryService::class)->expireCustomer($user);
            $user->refresh();
        }
        if ($user->isCustomer() && $user->isCustomerApprovalRejected()) {
            return redirect()
                ->route($registerRoute, ['phone' => $phone])
                ->with('info', \App\Support\AuthOtp::pendingExpiredLoginMessage());
        }

        if ($user->isAwaitingApprovalForRegisterOtp()) {
            return redirect($this->registration->openRegisterOtpPage($request, $user))
                ->with('info', \App\Support\AuthOtp::awaitingApprovalOtpNotice($user->isCustomer()));
        }
        if ($user->role === 'driver') {
            $driverProfile = DriverProfile::query()->where('user_id', $user->id)->first();
            if (! $driverProfile) {
                return redirect()
                    ->route('driver.register', ['phone' => $phone])
                    ->with('info', 'Số điện thoại chưa có tài khoản tài xế. Vui lòng đăng ký.');
            }
            if ($driverProfile->isPendingApproval() && $driverProfile->isPendingApprovalExpired()) {
                app(\App\Services\PendingApprovalExpiryService::class)->expireDriver($driverProfile);
                $driverProfile->refresh();
            }
            if ($driverProfile->isRejected()) {
                return redirect()
                    ->route('driver.register', ['phone' => $phone])
                    ->with('info', \App\Support\AuthOtp::pendingExpiredLoginMessage());
            }
        }

        if ($this->registration->shouldResumeRegisterOtp($user)) {
            $otpUrl = $this->registration->beginRegisterOtpResume($request, $user);

            return redirect($otpUrl)
                ->with('success', \App\Support\AuthOtp::adminProvideHint());
        }

        if ($block = $user->loginBlockMessage()) {
            return back()
                ->withErrors(['login' => $block])
                ->withInput($request->only('phone'));
        }

        if ($this->loginGuard->isLocked($user)) {
            return back()
                ->withErrors(['login' => $this->loginGuard->lockMessage($user)])
                ->withInput($request->only('phone'));
        }

        $authenticated = WebAuth::attemptPhone($phone, $validated['password']);

        if (! $authenticated) {
            $this->loginGuard->recordFailure($user);
            $user->refresh();

            if ($this->loginGuard->isLocked($user)) {
                return back()
                    ->withErrors(['login' => $this->loginGuard->lockMessage($user)])
                    ->withInput($request->only('phone'));
            }

            $left = $this->loginGuard->remainingAttempts($user);

            return back()
                ->withErrors([
                    'login' => 'PIN không đúng. Còn '.$left.' lần thử trước khi tạm khóa.',
                ])
                ->withInput($request->only('phone'));
        }

        $user = $authenticated;

        if ($user->role === 'admin') {
            Auth::logout();

            return redirect()
                ->route('admin.login')
                ->withErrors(['login' => 'Tài khoản quản trị vui lòng đăng nhập tại đây.']);
        }

        if ($blockMessage = $user->loginBlockMessage()) {
            return back()
                ->withErrors(['login' => $blockMessage])
                ->withInput($request->only('phone'));
        }

        $this->loginGuard->clearFailures($user);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->role === 'customer') {
            $this->customerAccounts->linkExistingBookings($user);
        }

        if ($user->role === 'driver') {
            $profile = DriverProfile::query()->where('user_id', $user->id)->first();
            if ($profile) {
                try {
                    $this->driverAvailability->resetForWebLogin($profile);
                } catch (\Throwable) {
                    // Không chặn đăng nhập nếu đồng bộ trạng thái tài xế lỗi.
                }
            }
        }

        $intended = $request->session()->pull('url.intended');
        if ($intended && RoleDashboard::urlAllowedForRole($intended, $user->role)) {
            return redirect($intended);
        }

        return redirect(RoleDashboard::forUser($user, $request));
    }

    public function showRegister(Request $request)
    {
        $fromDriver = $this->isDriverAuthAudience($request);
        AuthAudience::rememberDriver($request, $fromDriver);
        $returnUrl = $fromDriver ? route('driver.dashboard') : null;
        $prefillPhone = AuthIdentifier::normalizePhone((string) (
            $request->query('phone', '') ?: old('phone', '')
        ));

        return view('auth.register', [
            'returnUrl'    => $returnUrl,
            'fromDriver'   => $fromDriver,
            'prefillPhone' => $prefillPhone !== '' ? $prefillPhone : null,
        ]);
    }

    public function register(RegisterDriverRequest $request)
    {
        $validated = $request->validated();

        try {
            $result = $this->registration->registerDriver($validated, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        $user = $result['user'];
        AuthAudience::rememberFromUser($request, $user);
        $profile = DriverProfile::query()->where('user_id', $user->id)->first();
        if ($profile) {
            app(\App\Services\AdminOperatorAlertService::class)->recordDriverRegistrationPending($profile);
        }
        app(\App\Services\UserInboxService::class)->notifyRegistrationSuccess($user);

        return redirect($this->registration->openRegisterOtpPage($request, $user))
            ->with('success', \App\Support\AuthOtp::registerSuccess());
    }

    /** Login/register TX vs khách — cùng form login, khác URL đăng ký khi SĐT chưa có. */
    private function isDriverAuthAudience(Request $request): bool
    {
        return AuthAudience::isDriver($request);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $wasAdmin = $user && $user->role === 'admin';

        if ($user && $user->role === 'driver') {
            $profile = DriverProfile::query()->where('user_id', $user->id)->first();
            if ($profile) {
                $this->driverAvailability->endWebSession($profile);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route($wasAdmin ? 'admin.login' : 'login')
            ->with('success', 'Bạn đã đăng xuất.');
    }
}
