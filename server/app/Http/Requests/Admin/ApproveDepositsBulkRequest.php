<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'transaction_ids'   => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct', 'exists:driver_wallet_transactions,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'transaction_ids.required' => 'Vui lòng chọn ít nhất một đơn nạp.',
            'transaction_ids.min'      => 'Vui lòng chọn ít nhất một đơn nạp.',
        ];
    }
}
