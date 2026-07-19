<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'km_rate_under_100'         => ['required', 'integer', 'min:0'],
            'km_rate_over_100'          => ['required', 'integer', 'min:0'],
            'intra_flat_max_km'         => ['required', 'integer', 'min:0', 'max:50'],
            'intra_flat_price'          => ['required', 'integer', 'min:0'],
            'rounding_unit'             => ['required', 'integer', 'min:1000'],
            'app_commission'            => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_first' => ['required', 'numeric', 'min:0', 'max:100'],
            'booking_qr_discount'       => ['required', 'numeric', 'min:0', 'max:100'],
            'driver_invite_qr_discount' => ['required', 'numeric', 'min:0', 'max:100'],
            'rain_surcharge_enabled'    => ['nullable', 'boolean'],
            'sync_driver_invite_discount' => ['nullable', 'boolean'],
        ];
    }
}
