<?php

namespace App\Http\Requests\Auth;

use App\Services\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(
            app(RegistrationService::class)->driverRules(),
            ['register_mode' => ['required', Rule::in(['driver'])]],
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $pwdHint = 'Mật khẩu tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và số.';

        return [
            'phone.required'     => 'Vui lòng nhập số điện thoại.',
            'password.required'  => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Nhập lại mật khẩu không khớp.',
            'password.min'       => $pwdHint,
            'password.mixed'     => $pwdHint,
            'password.numbers'   => $pwdHint,
            'password.letters'   => $pwdHint,
            'password_confirmation.required' => 'Vui lòng nhập lại mật khẩu.',
            'terms.accepted'     => 'Vui lòng đồng ý với điều khoản.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'phone'    => 'số điện thoại',
            'password' => 'mật khẩu',
        ];
    }
}
