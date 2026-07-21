<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePricingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->input('form_scope') === 'qr') {
            return [
                'referral_commission_first' => ['required', 'numeric', 'min:0', 'max:100'],
            ];
        }

        return [
            'km_rate_under_100'      => ['required', 'integer', 'min:0'],
            'km_rate_over_100'       => ['required', 'integer', 'min:0'],
            'intra_flat_max_km'      => ['required', 'integer', 'min:0', 'max:50'],
            'intra_flat_price'       => ['required', 'integer', 'min:0'],
            'rounding_unit'          => ['required', 'integer', 'min:1000'],
            'app_commission'         => ['required', 'numeric', 'min:0', 'max:100'],
            'rain_surcharge_enabled' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->input('form_scope') === 'qr') {
            throw new HttpResponseException(
                redirect()->route('admin.referrals', ['tab' => 'rules'])
                    ->withErrors($validator)
                    ->withInput()
            );
        }

        parent::failedValidation($validator);
    }
}
