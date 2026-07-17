<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDriverRequest;
use App\Models\DriverProfile;
use App\Services\DriverAvailabilityService;
use App\Services\RegistrationService;
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
    ) {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = WebAuth::attempt($validated['phone'], $validated['password']);

        if (! $user) {
            return back()
                ->withErrors(['login' => 'Tài khoản hoặc mật khẩu không đúng'])
                ->withInput();
        }

        if ($blockMessage = $user->loginBlockMessage()) {
            return back()
                ->withErrors(['login' => $blockMessage])
                ->withInput();
        }

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
        return view('auth.register');
    }

    public function register(RegisterDriverRequest $request)
    {
        $validated = $request->validated();

        try {
            $user = $this->registration->registerDriver($validated, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        if ($user->status === 'active') {
            Auth::login($user);
            $request->session()->regenerate();

            if ($user->role === 'driver') {
                $profile = DriverProfile::query()->where('user_id', $user->id)->first();
                if ($profile) {
                    $this->driverAvailability->resetForWebLogin($profile);
                }
            }

            return redirect(RoleDashboard::route($user->role))
                ->with('success', 'Đăng ký thành công. Chào mừng bạn đến với ' . config('app.name') . '!');
        }

        return redirect()->route('login')
            ->with('success', 'Đăng ký thành công. Hồ sơ cần duyệt trước khi đăng nhập trong vòng từ 3 đến 5 ngày làm việc.');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role === 'driver') {
            $profile = DriverProfile::query()->where('user_id', $user->id)->first();
            if ($profile) {
                $this->driverAvailability->endWebSession($profile);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
