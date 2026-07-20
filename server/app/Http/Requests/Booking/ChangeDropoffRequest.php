<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ChangeDropoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_reference'  => ['required', 'string', 'max:64'],
            'dropoff_address'    => ['nullable', 'string', 'max:255'],
            'dropoff_detail'     => ['required', 'string', 'max:500'],
            'dropoff_lat'        => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'        => ['required', 'numeric', 'between:-180,180'],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
