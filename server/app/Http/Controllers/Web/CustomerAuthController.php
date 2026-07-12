<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\RegistrationService;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly WebAuthnService $webauthn,
        private readonly CustomerAccountService $accounts,
    ) {
    }

    public function showRegister()
    {
        return view('auth.register-customer');
    }

    public function register(Request $request)
    {
        $validated = $request->validate($this->registration->customerRules());

        $user = $this->registration->registerCustomer($validated);

        $request->session()->put('pending_auth.user_id', $user->id);
        $request->session()->put('pending_auth.intended', route('customer.account', [], false));

        return redirect()
            ->route('auth.biometric')
            ->with('success', 'Đăng ký thành công. Thiết lập xác thực khuôn mặt/vân tay để hoàn tất.');
    }

    public function showBiometric(Request $request)
    {
        $user = $this->resolveBiometricUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.biometric', [
            'user'            => $user,
            'hasCredentials'  => $this->webauthn->userHasCredentials($user),
            'webauthnEnabled' => true,
        ]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $user = $this->resolveBiometricUser($request);
        if (! $user) {
            return response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        return response()->json($this->webauthn->registrationOptions($user));
    }

    public function verifyRegistration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $user = $this->resolveBiometricUser($request);
        if (! $user) {
            return response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        try {
            $this->webauthn->verifyRegistration($user, $validated['credential']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Không thể đăng ký sinh trắc học. Vui lòng thử lại.'], 422);
        }

        return $this->completeCustomerLogin($request, $user);
    }

    public function assertionOptions(Request $request): JsonResponse
    {
        $user = $this->resolveBiometricUser($request);
        if (! $user) {
            return response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        if (! $this->webauthn->userHasCredentials($user)) {
            return response()->json(['message' => 'Chưa có sinh trắc học.'], 422);
        }

        return response()->json($this->webauthn->assertionOptions($user));
    }

    public function verifyAssertion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $user = $this->resolveBiometricUser($request);
        if (! $user) {
            return response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        try {
            $this->webauthn->verifyAssertion($user, $validated['credential']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Xác thực sinh trắc thất bại. Vui lòng thử lại.'], 422);
        }

        return $this->completeCustomerLogin($request, $user);
    }

    public function skipBiometric(Request $request): JsonResponse
    {
        $user = $this->resolveBiometricUser($request);
        if (! $user) {
            return response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        return $this->completeCustomerLogin($request, $user, biometricVerified: false);
    }

    private function completeCustomerLogin(Request $request, User $user, bool $biometricVerified = true): JsonResponse
    {
        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->forget(['pending_auth.user_id']);
        $request->session()->put('customer_biometric_verified', $biometricVerified);

        $this->accounts->linkExistingBookings($user);

        $redirect = $request->session()->pull('pending_auth.intended', route('customer.account', [], false));

        return response()->json([
            'redirect' => $redirect,
            'user'     => $this->accounts->profileSummary($user),
        ]);
    }

    private function pendingAuthUser(Request $request): ?User
    {
        $userId = $request->session()->get('pending_auth.user_id');

        if (! $userId) {
            return null;
        }

        $user = User::query()->find($userId);

        return ($user && $user->role === 'customer' && $user->status === 'active') ? $user : null;
    }

    private function resolveBiometricUser(Request $request): ?User
    {
        $pending = $this->pendingAuthUser($request);
        if ($pending) {
            return $pending;
        }

        $authUser = Auth::user();
        if ($authUser
            && $authUser->role === 'customer'
            && $authUser->status === 'active'
            && ! $request->session()->get('customer_biometric_verified')) {
            return $authUser;
        }

        return null;
    }
}
