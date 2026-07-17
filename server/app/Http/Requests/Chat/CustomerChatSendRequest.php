<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class CustomerChatSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_reference' => ['required', 'string', 'max:64'],
            'after_id'          => ['nullable', 'integer', 'min:0'],
            'body'              => ['required', 'string', 'max:1000'],
        ];
    }
}
