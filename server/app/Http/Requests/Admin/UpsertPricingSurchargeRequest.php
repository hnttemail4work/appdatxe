<?php

namespace App\Http\Requests\Admin;

use App\Models\PricingSurchargeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPricingSurchargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type'          => ['required', Rule::in([
                PricingSurchargeRule::TYPE_HOLIDAY,
                PricingSurchargeRule::TYPE_PEAK,
                PricingSurchargeRule::TYPE_RAIN,
            ])],
            'name'          => ['required', 'string', 'max:120'],
            'mode'          => ['required', Rule::in([
                PricingSurchargeRule::MODE_PERCENT,
                PricingSurchargeRule::MODE_FIXED,
            ])],
            'value'         => ['required', 'numeric', 'min:0'],
            'starts_on'     => ['nullable', 'date'],
            'ends_on'       => ['nullable', 'date', 'after_or_equal:starts_on'],
            'days_of_week'  => ['nullable', 'array'],
            'days_of_week.*'=> ['integer', 'min:0', 'max:6'],
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
