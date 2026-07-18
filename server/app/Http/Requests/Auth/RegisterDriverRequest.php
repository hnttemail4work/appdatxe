<?php

namespace App\Http\Requests\Auth;

use App\Services\RegistrationService;
use App\Support\AuthMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(
            app(RegistrationService::class)->driverRules(),
            ['register_mode' => ['required', Rule::in(['driver'])]],
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(AuthMessages::authCommon(), [
            'vehicle_license_plate.required' => 'Vui lòng nhập biển số xe.',
            'vehicle_type.required'          => 'Vui lòng chọn loại xe.',
            'bank_name.required'             => 'Vui lòng chọn ngân hàng.',
            'bank_account.required'          => 'Vui lòng nhập số tài khoản.',
            'photo_portrait.required'        => 'Vui lòng chọn ảnh chân dung.',
            'photo_id_card.required'         => 'Vui lòng chọn ảnh CCCD mặt trước.',
            'photo_id_card_back.required'    => 'Vui lòng chọn ảnh CCCD mặt sau.',
            'photo_license_front.required'   => 'Vui lòng chọn ảnh GPLX mặt trước.',
            'photo_vehicles.required'        => 'Vui lòng chọn ít nhất 1 ảnh xe.',
            'photo_vehicles.min'             => 'Vui lòng chọn ít nhất 1 ảnh xe.',
            'photo_portrait.mimes'           => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'photo_id_card.mimes'            => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'photo_id_card_back.mimes'       => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'photo_license_front.mimes'      => 'Ảnh phải là JPG, PNG hoặc WebP.',
            'photo_vehicles.*.mimes'         => 'Ảnh xe phải là JPG, PNG hoặc WebP.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'phone'                 => 'số điện thoại',
            'password'              => 'PIN',
            'password_confirmation' => 'nhập lại PIN',
            'email'                 => 'email',
            'name'                  => 'họ tên',
            'vehicle_license_plate' => 'biển số',
            'vehicle_type'          => 'loại xe',
            'bank_name'             => 'ngân hàng',
            'bank_account'          => 'số tài khoản',
            'terms'                 => 'điều khoản',
        ];
    }
}
