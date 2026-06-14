<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use App\Support\RoleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng'])->withInput();
        }

        $request->session()->regenerate();

        if (Auth::user()->status !== 'active') {
            Auth::logout();
            return back()->withErrors(['email' => 'Tài khoản của bạn chưa được kích hoạt hoặc đã bị tạm ngưng.'])->withInput();
        }

        $role = Auth::user()->role;
        $redirect = RoleDashboard::route($role);

        $intended = $request->session()->pull('url.intended');
        if ($intended && RoleDashboard::urlAllowedForRole($intended, $role)) {
            return redirect($intended);
        }

        return redirect($redirect);
    }

    public function showRegister(Request $request)
    {
        $mode = old('register_mode', $request->query('mode', 'customer'));

        if (! in_array($mode, ['customer', 'driver'], true)) {
            $mode = 'customer';
        }

        return view('auth.register', compact('mode'));
    }

    public function register(Request $request)
    {
        $mode = $request->input('register_mode', 'customer');

        if (! in_array($mode, ['customer', 'driver'], true)) {
            return back()->withErrors(['register_mode' => 'Loại đăng ký không hợp lệ.'])->withInput();
        }

        $rules = match ($mode) {
            'driver' => $this->registration->driverRules(),
            default  => $this->registration->customerRules(),
        };

        $validated = $request->validate(array_merge($rules, [
            'register_mode' => ['required', Rule::in(['customer', 'driver'])],
        ]));

        $user = $mode === 'driver'
            ? $this->registration->registerDriver($validated)
            : $this->registration->registerCustomer($validated);

        if ($user->status === 'active') {
            Auth::login($user);

            return redirect(RoleDashboard::route($user->role))
                ->with('success', 'Đăng ký thành công. Chào mừng bạn đến với ' . config('app.name') . '!');
        }

        $message = $mode === 'driver'
            ? 'Đăng ký tài xế thành công. Hồ sơ đang chờ quản lý/admin phê duyệt trước khi đăng nhập.'
            : 'Đăng ký thành công.';

        return redirect()->route('login')->with('success', $message);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
