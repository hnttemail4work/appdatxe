<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Support\AdminBootstrapAccount;
use App\Support\RoleDashboard;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.admin-login');
    }

    public function login(AdminLoginRequest $request)
    {
        $user = AdminBootstrapAccount::attempt(
            (string) $request->validated('login'),
            (string) $request->validated('password'),
        );

        if (! $user) {
            return back()
                ->withErrors(['login' => 'Tài khoản hoặc mật khẩu không đúng.'])
                ->withInput($request->only('login'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        $intended = $request->session()->pull('url.intended');
        if ($intended && RoleDashboard::urlAllowedForRole($intended, 'admin')) {
            return redirect($intended);
        }

        return redirect(RoleDashboard::route('admin'));
    }
}
