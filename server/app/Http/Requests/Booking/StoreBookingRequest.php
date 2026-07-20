<?php

namespace App\Http\Requests\Booking;

use App\Support\DriverVehicleOptions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['service_date', 'pickup_time'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }
            $value = trim((string) $this->input($field));
            $merge[$field] = $value === '' ? null : $value;
        }
        if (! $this->filled('payment_method')) {
            $merge['payment_method'] = 'cash';
        }

        // Lấy từ hồ sơ khách đã duyệt — không nhập lại trên form đặt xe.
        $user = $this->user();
        if ($user && $user->role === 'customer') {
            $phone = trim((string) ($user->phone ?? ''));
            $name = trim((string) ($user->name ?? ''));
            $nameLooksLikePhone = $name === ''
                || ($phone !== '' && preg_replace('/\D+/', '', $name) === preg_replace('/\D+/', '', $phone))
                || (bool) preg_match('/^[\d\s.+()-]+$/', $name);

            $merge['contact_phone'] = $phone;
            $merge['passenger_name'] = $nameLooksLikePhone ? '' : $name;
            $merge['passenger_gender'] = in_array($user->gender, ['male', 'female'], true)
                ? $user->gender
                : 'male';
            $merge['passenger_age'] = $user->age();
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'capacity'         => ['required', 'integer', 'min:1', 'max:60'],
            'vehicle_type'     => ['nullable', 'string', Rule::in(DriverVehicleOptions::allowedKeys())],
            'service_date'     => ['nullable', 'date', 'after_or_equal:today', 'required_with:pickup_time'],
            'pickup_time'      => ['nullable', 'string', 'max:8', 'regex:/^\d{1,2}:\d{2}$/', 'required_with:service_date'],
            'passenger_name'   => ['required', 'string', 'max:255'],
            'passenger_gender' => ['required', 'in:male,female'],
            'passenger_age'    => ['required', 'integer', 'min:1', 'max:120'],
            'contact_phone'    => ['required', 'string', 'max:30'],
            'pickup_address'   => ['nullable', 'string', 'max:255'],
            'dropoff_address'  => ['nullable', 'string', 'max:255'],
            'pickup_detail'    => ['required', 'string', 'max:500'],
            'dropoff_detail'   => ['required', 'string', 'max:500'],
            'pickup_lat'       => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'       => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat'      => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'      => ['required', 'numeric', 'between:-180,180'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'referral_code'    => ['nullable', 'string', 'max:32'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
            'payment_method'   => ['required', 'in:cash,wallet'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_date.after_or_equal' => 'Ngày đi phải từ hôm nay trở đi.',
            'service_date.required_with'  => 'Vui lòng chọn ngày đón khi đặt sau.',
            'pickup_time.regex'           => 'Giờ đón không hợp lệ.',
            'pickup_time.required_with'   => 'Vui lòng chọn giờ đón khi đặt sau.',
            'pickup_lat.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'pickup_lng.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'dropoff_lat.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'dropoff_lng.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'pickup_detail.required'      => 'Vui lòng chọn điểm đón trên bản đồ.',
            'dropoff_detail.required'     => 'Vui lòng chọn điểm trả trên bản đồ.',
            'payment_method.required'     => 'Vui lòng chọn hình thức thanh toán.',
            'payment_method.in'           => 'Hình thức thanh toán không hợp lệ.',
            'contact_phone.required'      => 'Tài khoản chưa có số điện thoại.',
        ];
    }

    /**
     * Giữ đúng hành vi cũ: luôn redirect về trang chủ kèm lỗi + input,
     * không tự chuyển sang JSON dù request có Accept: application/json.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('home')->withErrors($validator)->withInput(),
        );
    }
}
