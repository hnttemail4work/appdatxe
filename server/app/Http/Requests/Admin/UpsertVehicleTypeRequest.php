<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertVehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('vehicleType')?->id;

        return [
            'key'           => [
                $id ? 'nullable' : 'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('vehicle_types', 'key')->ignore($id),
            ],
            'label'         => ['required', 'string', 'max:120'],
            'seats'         => ['nullable', 'integer', 'min:1', 'max:60'],
            'family'        => ['nullable', 'string', 'max:32'],
            'price_percent' => ['required', 'numeric', 'min:50', 'max:500'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'     => ['nullable', 'boolean'],
            'image'         => ['nullable', 'image', 'max:5120'],
            'remove_image'  => ['nullable', 'boolean'],
        ];
    }
}
