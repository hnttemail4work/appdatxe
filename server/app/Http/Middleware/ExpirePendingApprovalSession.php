<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PendingApprovalExpiryService;
use App\Support\AuthAudience;
use App\Support\AuthOtp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hết TTL chờ duyệt mà admin chưa duyệt → chuyển "Đã từ chối", đăng xuất về login.
 */
class ExpirePendingApprovalSession
{
    public function __construct(
        private readonly PendingApprovalExpiryService $expiry,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User) {
            if ($user->isCustomerApprovalPending() && $user->isCustomerPendingApprovalExpired()) {
                $this->expiry->expireCustomer($user);

                return $this->forceLogin($request, $user);
            }

            if ($user->role === 'driver') {
                $profile = $user->relationLoaded('driverProfile')
                    ? $user->driverProfile
                    : $user->driverProfile()->first();
                if ($profile && $profile->isPendingApprovalExpired()) {
                    $this->expiry->expireDriver($profile);

                    return $this->forceLogin($request, $user);
                }
            }
        }

        // Phiên OTP đăng ký chưa login: hết hạn → từ chối hồ sơ, về login.
        $pendingId = $request->session()->get('pending_register_otp.user_id');
        if ($pendingId && ! $user) {
            $pending = User::query()->with('driverProfile')->find($pendingId);
            if ($pending instanceof User) {
                $expired = false;
                if ($pending->isCustomer() && $pending->isCustomerPendingApprovalExpired()) {
                    $expired = $this->expiry->expireCustomer($pending);
                } elseif ($pending->role === 'driver' && $pending->driverProfile?->isPendingApprovalExpired()) {
                    $expired = $this->expiry->expireDriver($pending->driverProfile);
                }

                $rejectedDriver = $pending->role === 'driver' && $pending->driverProfile?->isRejected();
                if ($expired || ($pending->isCustomer() && $pending->isCustomerApprovalRejected()) || $rejectedDriver) {
                    AuthAudience::rememberFromUser($request, $pending);
                    $request->session()->forget(['pending_register_otp.user_id']);

                    if ($request->routeIs('auth.register.otp', 'auth.register.otp.verify', 'auth.register.otp.resend')) {
                        return redirect()
                            ->to(AuthAudience::loginUrl($request))
                            ->with('info', AuthOtp::pendingExpiredLoginMessage());
                    }
                }
            } else {
                $request->session()->forget(['pending_register_otp.user_id']);
            }
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->routeIs(
            'login',
            'driver.login',
            'admin.login',
            'logout',
            'password.reset.*',
        );
    }

    private function forceLogin(Request $request, ?User $user = null): Response
    {
        if ($user) {
            AuthAudience::rememberFromUser($request, $user);
        }
        $loginUrl = AuthAudience::loginUrl($request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => AuthOtp::pendingExpiredLoginMessage(),
                'redirect' => $loginUrl,
            ], 401);
        }

        return redirect()
            ->to($loginUrl)
            ->with('info', AuthOtp::pendingExpiredLoginMessage());
    }
}
