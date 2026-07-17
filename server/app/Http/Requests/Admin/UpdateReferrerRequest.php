<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReferrerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'commission_percent'        => ['required', 'numeric', 'min:0', 'max:100'],
            'customer_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
