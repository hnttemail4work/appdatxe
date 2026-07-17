<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePushSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'events'  => ['nullable', 'array'],
            'events.*'=> ['string', 'max:64'],
            'vapid_public'  => ['nullable', 'string', 'max:255'],
            'vapid_private' => ['nullable', 'string', 'max:255'],
            'vapid_subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
