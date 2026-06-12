<?php

namespace App\Http\Requests\Api\Operator;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'operator' || $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'route_id' => ['required', 'exists:routes,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_name' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'available_seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draft,scheduled,running,completed,cancelled'],
        ];
    }
}
