<?php

namespace App\Http\Requests\Admin;

use App\Support\RouteDistanceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hub = RouteDistanceCatalog::HUB;

        return [
            'destination' => [
                'required',
                'string',
                'max:100',
                Rule::notIn([$hub]),
                Rule::unique('routes', 'destination')->where(
                    fn ($q) => $q->where('departure', $hub)->where('is_active', true),
                ),
            ],
            'distance_km' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'destination.unique' => 'Điểm đến này đã có trong danh sách.',
            'destination.not_in' => 'Không thể thêm trùng trung tâm ' . RouteDistanceCatalog::HUB . '.',
        ];
    }
}
