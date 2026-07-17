<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDismissBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_ids'   => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'booking_ids.required' => 'Vui lòng chọn ít nhất một đơn.',
            'booking_ids.min'      => 'Vui lòng chọn ít nhất một đơn.',
        ];
    }
}
