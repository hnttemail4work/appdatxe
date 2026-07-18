<?php

namespace App\Http\Requests\Booking;

use App\Support\DriverVehicleOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'capacity'           => ['required', 'integer', 'min:1', 'max:60'],
            'vehicle_type'       => ['nullable', 'string', Rule::in(DriverVehicleOptions::allowedKeys())],
            'pickup_address'     => ['nullable', 'string', 'max:255'],
            'dropoff_address'    => ['nullable', 'string', 'max:255'],
            'pickup_detail'      => ['required', 'string', 'max:500'],
            'dropoff_detail'     => ['required', 'string', 'max:500'],
            'pickup_lat'         => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'         => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat'        => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'        => ['required', 'numeric', 'between:-180,180'],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
        ];
    }
}
