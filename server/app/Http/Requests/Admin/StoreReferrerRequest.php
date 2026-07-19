<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreReferrerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ];
    }

    /** Giữ đúng hành vi cũ: luôn redirect về admin.referrals, không tự chuyển JSON. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('admin.referrals', ['tab' => 'codes'])->withErrors($validator)->withInput(),
        );
    }
}
