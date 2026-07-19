<?php

namespace App\Http\Requests\Admin;

use App\Support\DriverSoundPresets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSoundSettingsRequest extends FormRequest
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
            'preset'  => ['required', 'string', Rule::in(DriverSoundPresets::keys())],
        ];
    }
}
