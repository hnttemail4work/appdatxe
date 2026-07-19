<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Services\RegistrationService;
use App\Support\AuthAudience;

class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registration,
    ) {
    }

    public function showRegister(\Illuminate\Http\Request $request)
    {
        AuthAudience::rememberDriver($request, false);

        return view('auth.register-customer');
    }

    public function register(RegisterCustomerRequest $request)
    {
        $validated = $request->validated();

        try {
            $result = $this->registration->registerCustomer($validated, $request);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        $user = $result['user'];
        AuthAudience::rememberFromUser($request, $user);
        app(\App\Services\AdminOperatorAlertService::class)->recordCustomerRegistrationPending($user);
        app(\App\Services\UserInboxService::class)->notifyRegistrationSuccess($user);

        return redirect($this->registration->openRegisterOtpPage($request, $user))
            ->with('success', \App\Support\AuthOtp::registerSuccess());
    }
}
