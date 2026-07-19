<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveDepositsBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'regex:/^(driver|customer):\d+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Vui lòng chọn ít nhất một đơn nạp.',
            'items.min'      => 'Vui lòng chọn ít nhất một đơn nạp.',
            'items.*.regex'  => 'Mã đơn nạp không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }
            if (count($items) !== count(array_unique($items))) {
                $validator->errors()->add('items', 'Danh sách đơn nạp bị trùng.');
            }
        });
    }
}
