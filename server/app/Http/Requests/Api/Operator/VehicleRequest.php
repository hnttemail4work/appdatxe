<?php

namespace App\Http\Requests\Api\Operator;

use Illuminate\Foundation\Http\FormRequest;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'operator';
    }

    public function rules(): array
    {
        return [
            'operator_id' => ['nullable', 'exists:users,id'],
            'license_plate' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:limousine,sedan,suv'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,maintenance,inactive'],
        ];
    }
}
