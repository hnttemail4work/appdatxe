<?php

namespace App\Http\Requests\Api\Customer;

use App\Support\SouthernProvinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure' => ['nullable', 'string', 'max:255', Rule::when(
                fn (array $input) => filled($input['departure'] ?? null),
                SouthernProvinces::inRule(),
            )],
            'destination' => ['nullable', 'string', 'max:255', Rule::when(
                fn (array $input) => filled($input['destination'] ?? null),
                SouthernProvinces::inRule(),
            )],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'vehicle_type' => ['nullable', 'in:limousine,sedan,suv'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
