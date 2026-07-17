<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteDistancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'routes'               => ['required', 'array'],
            'routes.*.id'          => ['required', 'integer', 'exists:routes,id'],
            'routes.*.distance_km' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
    }
}
