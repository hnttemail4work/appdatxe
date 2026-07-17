<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'app_name'              => ['nullable', 'string', 'max:80'],
            'brand_title'           => ['nullable', 'string', 'max:40'],
            'brand_tagline'         => ['nullable', 'string', 'max:80'],
            'pwa_guest_short_name'  => ['nullable', 'string', 'max:24'],
            'pwa_driver_short_name' => ['nullable', 'string', 'max:24'],
            'app_icon'              => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif,svg', 'max:2048'],
            'remove_app_icon'       => ['nullable', 'boolean'],
        ];
    }
}
