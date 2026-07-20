<?php

namespace App\Http\Requests\Auth;

use App\Services\RegistrationService;
use App\Support\AuthMessages;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function () {
            $redirect = app(RegistrationService::class)->redirectIfPhoneBlocksRegister($this, false);
            if ($redirect) {
                throw new HttpResponseException($redirect);
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(AuthMessages::authCommon(), [
            'photo_id_card.required'      => 'Vui lòng chọn ảnh CCCD mặt trước.',
            'photo_id_card_back.required' => 'Vui lòng chọn ảnh CCCD mặt sau.',
            'photo_id_card.mimes'         => 'Ảnh CCCD mặt trước phải là JPG, PNG hoặc WebP.',
            'photo_id_card_back.mimes'    => 'Ảnh CCCD mặt sau phải là JPG, PNG hoặc WebP.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'phone'                 => 'số điện thoại',
            'password'              => 'PIN',
            'password_confirmation' => 'nhập lại PIN',
            'photo_id_card'         => 'ảnh CCCD mặt trước',
            'photo_id_card_back'    => 'ảnh CCCD mặt sau',
            'terms'                 => 'điều khoản',
        ];
    }
}
