<?php

namespace App\Http\Requests\Customer;

use App\Support\DriverSoundPresets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale'        => ['required', Rule::in(['vi', 'en'])],
            'sound_enabled' => ['sometimes', 'boolean'],
            'sound_preset'  => ['required', Rule::in(DriverSoundPresets::keys())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sound_enabled' => $this->boolean('sound_enabled'),
            'sound_preset'  => DriverSoundPresets::normalize($this->input('sound_preset')),
        ]);
    }
}
