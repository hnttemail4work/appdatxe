<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_name'    => ['required', 'string', 'max:120'],
            'bank_bin'     => ['required', 'string', 'max:20'],
            'account'      => ['required', 'string', 'max:40'],
            'account_name' => ['required', 'string', 'max:120'],
        ];
    }
}
