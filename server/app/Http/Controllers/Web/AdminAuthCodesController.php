<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuthVerificationCode;
use App\Services\AuthVerificationService;
use Illuminate\Http\Request;

class AdminAuthCodesController extends Controller
{
    public function __construct(
        private readonly AuthVerificationService $verification,
    ) {
    }

    public function index()
    {
        $codes = $this->verification->adminActiveCodes();

        return view('admin.auth-codes', [
            'codes' => $codes,
        ]);
    }

    public function issueReset(Request $request, AuthVerificationCode $code)
    {
        if ($code->purpose !== AuthVerificationCode::PURPOSE_PASSWORD_RESET_REQUEST) {
            return back()->withErrors(['code' => 'Không phải yêu cầu đặt lại mật khẩu.']);
        }

        try {
            $issued = $this->verification->adminIssuePasswordReset($code);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()
            ->with('success', 'Đã cấp mã đặt lại mật khẩu ('.\App\Support\AuthOtp::ttlLabel().').')
            ->with('auth_code_issued', [
                'phone'   => $issued['code']->phone,
                'purpose' => $issued['code']->purpose,
                'code'    => $issued['plain'],
                'expires' => optional($issued['code']->expires_at)->format('H:i d/m'),
            ]);
    }
}
