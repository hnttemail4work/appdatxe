<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class DriverSendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body'  => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp,gif'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $body = trim((string) $this->input('body', ''));
            if ($body === '' && ! $this->file('image')) {
                $validator->errors()->add('body', 'Vui lòng nhập nội dung tin nhắn hoặc đính kèm ảnh.');
            }
        });
    }
}
