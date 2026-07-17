<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class TripStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
            'booking_reference'  => ['nullable', 'string', 'max:64'],
        ];
    }
}
