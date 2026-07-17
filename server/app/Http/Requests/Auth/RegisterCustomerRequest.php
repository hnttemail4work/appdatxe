<?php

namespace App\Http\Requests\Auth;

use App\Services\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(RegistrationService::class)->customerRules();
    }
}
