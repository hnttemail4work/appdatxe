<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use App\Support\RoleDashboard;
use App\Support\WebAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $user = WebAuth::attempt($validated['phone'], $validated['password']);

        if (! $user) {
            return back()
                ->withErrors(['login' => 'Số điện thoại hoặc mật khẩu không đúng'])
                ->withInput();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->status !== 'active') {
            Auth::logout();

            $message = $user->status === 'suspended'
                ? 'Tài khoản của bạn bị tạm ngưng.'
                : 'Tài khoản của bạn chưa được kích hoạt.';

            return back()
                ->withErrors(['login' => $message])
                ->withInput();
        }

        $role = $user->role;
        $redirect = RoleDashboard::route($role);

        $intended = $request->session()->pull('url.intended');
        if ($intended && RoleDashboard::urlAllowedForRole($intended, $role)) {
            return redirect($intended);
        }

        return redirect($redirect);
    }

    public function showRegister(Request $request)
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate(array_merge(
            $this->registration->driverRules(),
            ['register_mode' => ['required', Rule::in(['driver'])]],
        ));

        try {
            $user = $this->registration->registerDriver($validated, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        if ($user->status === 'active') {
            Auth::login($user);

            return redirect(RoleDashboard::route($user->role))
                ->with('success', 'Đăng ký thành công. Chào mừng bạn đến với ' . config('app.name') . '!');
        }

        return redirect()->route('login')
            ->with('success', 'Đăng ký thành công. Hồ sơ cần duyệt trước khi đăng nhập trong vòng từ 3 đến 5 ngày làm việc.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
