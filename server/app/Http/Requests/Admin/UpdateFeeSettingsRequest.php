<?php

namespace App\Http\Requests\Admin;

use App\Support\VehicleTypePricing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $typeRules = [];
        foreach (VehicleTypePricing::priceableKeys() as $type) {
            $typeRules['vehicle_type_percents.' . $type] = ['nullable', 'numeric', 'min:50', 'max:500'];
        }

        return array_merge([
            'app_commission'        => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_first'  => ['required', 'numeric', 'min:0', 'max:100'],
            'referral_commission_repeat' => ['required', 'numeric', 'min:0', 'max:100'],
            'km_rate_under_100'   => ['required', 'integer', 'min:0'],
            'km_rate_over_100'    => ['required', 'integer', 'min:0'],
            'departure_plan_surcharge_today' => ['required', 'numeric', 'min:0', 'max:500'],
            'departure_plan_surcharge_tomorrow' => ['required', 'numeric', 'min:0', 'max:500'],
            'departure_plan_surcharge_later_per_day' => ['required', 'numeric', 'min:0', 'max:500'],
            'vehicle_type_step_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'vehicle_type_percents'   => ['nullable', 'array'],
            'vehicle_type_percents.*' => ['nullable', 'numeric', 'min:50', 'max:500'],
            'vehicle_type_baseline' => ['nullable', 'string', Rule::in(VehicleTypePricing::priceableKeys())],
        ], $typeRules);
    }
}
