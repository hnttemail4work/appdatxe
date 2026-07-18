<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthMessages;
use App\Support\AuthPhone;
use App\Support\PinPassword;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone'    => AuthPhone::rules(),
            'password' => PinPassword::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(AuthMessages::phone(), AuthMessages::pin());
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'phone'    => 'số điện thoại',
            'password' => 'PIN',
        ];
    }
}
