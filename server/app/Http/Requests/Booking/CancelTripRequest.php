<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CancelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_reference'       => ['required', 'string', 'max:64'],
            'contact_phone'           => ['nullable', 'string', 'max:30'],
            'booking_browser_id'      => ['nullable', 'string', 'max:128'],
            'cancellation_reason_id'  => ['nullable', 'integer'],
        ];
    }
}
