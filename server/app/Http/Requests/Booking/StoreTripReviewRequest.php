<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripReviewRequest extends FormRequest
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
            'sentiment'          => ['required', 'in:like,dislike'],
            'comment'            => ['nullable', 'string', 'max:500'],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
