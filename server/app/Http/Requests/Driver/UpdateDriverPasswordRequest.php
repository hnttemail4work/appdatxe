<?php

namespace App\Http\Requests\Driver;

use App\Support\PinPassword;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password'      => ['required', 'string'],
            'password'              => PinPassword::rules(confirmed: true),
            'password_confirmation' => ['required', 'string', 'digits:'.PinPassword::LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.digits'    => 'PIN phải gồm đúng 6 chữ số.',
            'password.confirmed' => 'Nhập lại PIN không khớp.',
        ];
    }
}
