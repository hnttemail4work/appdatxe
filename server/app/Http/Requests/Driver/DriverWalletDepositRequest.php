<?php

namespace App\Http\Requests\Driver;

use App\Support\DriverWalletConfig;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DriverWalletDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'min:' . DriverWalletConfig::MIN_DEPOSIT],
            'proof_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required'   => 'Vui lòng nhập số tiền nạp.',
            'amount.min'        => 'Số tiền nạp tối thiểu ' . DriverWalletConfig::minDepositFormatted() . '.',
            'proof_image.image' => 'Ảnh chụp chuyển khoản phải là file ảnh.',
            'proof_image.mimes' => 'Ảnh chụp chuyển khoản phải là JPG, PNG, WebP hoặc GIF.',
            'proof_image.max'   => 'Ảnh chụp chuyển khoản tối đa 5MB.',
        ];
    }

    /**
     * Giữ đúng hành vi cũ: JSON (kể cả request AJAX không có Accept: application/json)
     * trả lỗi dạng {message, errors}; web thì redirect về tab "deposit" kèm lỗi + input.
     */
    protected function failedValidation(Validator $validator): void
    {
        $wantsJson = $this->expectsJson() || $this->ajax();

        if ($wantsJson) {
            throw new HttpResponseException(response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422));
        }

        throw new HttpResponseException(
            redirect()->route('driver.dashboard', ['tab' => 'deposit'])
                ->withErrors($validator)
                ->withInput(),
        );
    }
}
