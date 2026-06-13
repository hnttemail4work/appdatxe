<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
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
        $redirect = match($role) {
            'admin'    => route('admin.dashboard'),
            'operator' => route('operator.dashboard'),
            'driver'   => route('driver.dashboard'),
            default    => route('customer.dashboard'),
        };

        return redirect()->intended($redirect);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['customer', 'operator'])],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'status' => $validated['role'] === 'operator' ? 'inactive' : 'active',
        ]);

        if ($user->role === 'operator') {
            MerchantProfile::query()->create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'] ?? $user->name,
                'tax_code' => $validated['tax_code'] ?? null,
                'kyc_status' => 'pending',
            ]);
        }

        if ($user->status === 'active') {
            Auth::login($user);
            $redirect = $user->role === 'customer' ? route('customer.dashboard') : route('operator.dashboard');
            return redirect($redirect)->with('success', 'Đăng ký thành công.');
        }

        return redirect()->route('login')->with('success', 'Đăng ký thành công. Tài khoản đang chờ duyệt.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Bạn đã đăng xuất.');
    }
}
