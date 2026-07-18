<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'login.required'    => 'Vui lòng nhập tài khoản.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'login'    => 'tài khoản',
            'password' => 'mật khẩu',
        ];
    }
}
