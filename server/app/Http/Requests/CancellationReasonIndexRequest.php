<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancellationReasonIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'audience'      => ['required', 'in:customer,driver'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
