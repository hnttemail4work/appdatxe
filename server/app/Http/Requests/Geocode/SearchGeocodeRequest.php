<?php

namespace App\Http\Requests\Geocode;

use Illuminate\Foundation\Http\FormRequest;

class SearchGeocodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q'        => ['required', 'string', 'min:2', 'max:200'],
            'province' => ['nullable', 'string', 'max:100'],
        ];
    }
}
