<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreReferrerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('mode', 'commission');
        if (! in_array($mode, ['commission', 'driver'], true)) {
            $mode = 'commission';
        }
        $this->merge(['mode' => $mode]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $mode = $this->input('mode', 'commission');

        if ($mode === 'driver') {
            return [
                'mode'              => ['required', Rule::in(['driver'])],
                'driver_profile_id' => ['required', 'integer', 'exists:driver_profiles,id'],
                'name'              => ['nullable', 'string', 'max:255'],
                'phone'             => ['nullable', 'string', 'max:30'],
            ];
        }

        return [
            'mode'               => ['required', Rule::in(['commission'])],
            'name'               => ['required', 'string', 'max:255'],
            'phone'              => ['required', 'string', 'max:30'],
            'commission_percent' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'driver_profile_id.required' => 'Vui lòng chọn tài xế để gán mã.',
            'name.required'              => 'Vui lòng nhập tên người giới thiệu.',
            'phone.required'             => 'Vui lòng nhập số điện thoại.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('admin.referrals', ['tab' => 'codes'])->withErrors($validator)->withInput(),
        );
    }
}
