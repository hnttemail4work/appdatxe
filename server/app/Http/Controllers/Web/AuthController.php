<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDriverRequest;
use App\Models\DriverProfile;
use App\Services\AuthLoginGuard;
use App\Services\DriverAvailabilityService;
use App\Services\RegistrationService;
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
    ) {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    /** JSON: missing | inactive | active — dùng trước bước nhập PIN. */
    public function checkPhone(Request $request)
    {
        $raw = (string) $request->input('phone', '');
        $phone = AuthIdentifier::normalizePhone($raw);

        if ($phone === '' || ! preg_match('/^0\d{8,10}$/', preg_replace('/\D/', '', $raw))) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Số điện thoại không hợp lệ.',
            ], 422);
        }

        $user = AuthIdentifier::findUserByPhone($phone);

        if (! $user) {
            return response()->json([
                'status'       => 'missing',
                'register_url' => route('customer.register', ['phone' => $phone]),
            ]);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'status'  => 'inactive',
                'message' => 'Tài khoản quản trị vui lòng đăng nhập tại /admin/login.',
            ]);
        }

        if ($block = $user->loginBlockMessage()) {
            return response()->json([
                'status'  => 'inactive',
                'message' => $block,
            ]);
        }

        return response()->json([
            'status' => 'active',
            'role'   => $user->role,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $phone = AuthIdentifier::normalizePhone($validated['phone']);
        $user = AuthIdentifier::findUserByPhone($phone);

        if (! $user) {
            return redirect()
                ->route('customer.register', ['phone' => $phone])
                ->with('info', 'Số điện thoại chưa có tài khoản. Vui lòng đăng ký.');
        }

        if ($user->role === 'admin') {
            return back()
                ->withErrors(['login' => 'Tài khoản quản trị vui lòng đăng nhập tại /admin/login.'])
                ->withInput($request->only('phone'));
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

        if ($user->role === 'customer') {
            $request->session()->regenerate();
            $intended = $request->session()->pull('url.intended');
            $request->session()->put('pending_auth.user_id', $user->id);
            if ($intended) {
                $request->session()->put('pending_auth.intended', $intended);
            }

            return redirect()->route('auth.biometric');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->role === 'driver') {
            $profile = DriverProfile::query()->where('user_id', $user->id)->first();
            if ($profile) {
                try {
                    $this->driverAvailability->resetForWebLogin($profile);
                } catch (\Throwable) {
                    // Không chặn đăng nhập nếu đồng bộ trạng thái tài xế lỗi.
                }
            }

            if ($user->must_change_password) {
                return redirect(RoleDashboard::forUser($user, $request));
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
        $fromDriver = $request->query('from') === 'driver';
        $returnUrl = $fromDriver ? route('driver.dashboard') : null;

        return view('auth.register', [
            'returnUrl' => $returnUrl,
            'fromDriver' => $fromDriver,
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
        $request->session()->put('pending_register_otp.user_id', $user->id);

        return redirect()
            ->route('auth.register.otp')
            ->with('success', 'Đã tạo tài khoản. Nhập mã OTP 6 số (admin sẽ cung cấp, hiệu lực 5 phút).');
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
