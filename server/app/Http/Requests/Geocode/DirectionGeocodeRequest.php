<?php

namespace App\Http\Requests\Geocode;

use Illuminate\Foundation\Http\FormRequest;

class DirectionGeocodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat'   => ['required', 'numeric', 'between:-90,90'],
            'dest_lng'   => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
