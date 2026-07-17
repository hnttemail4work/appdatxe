<?php

namespace App\Http\Requests\Booking;

use App\Support\DeparturePlan;
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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'capacity'         => ['required', 'integer', 'min:1', 'max:60'],
            'vehicle_type'     => ['nullable', 'string', Rule::in(DriverVehicleOptions::allowedKeys())],
            'service_date'     => ['nullable', 'date', 'after_or_equal:today'],
            'departure_plan'   => ['required', 'string', 'in:oneway,today,tomorrow,later'],
            'later_return_days'=> ['nullable', 'integer', 'min:' . DeparturePlan::MIN_LATER_RETURN_DAYS, 'max:' . DeparturePlan::MAX_LATER_RETURN_DAYS],
            'pickup_time'      => ['required', 'string', 'max:8', 'regex:/^\d{1,2}:\d{2}$/'],
            'passenger_name'   => ['required', 'string', 'max:255'],
            'passenger_gender' => ['nullable', 'in:male,female'],
            'passenger_age'    => ['nullable', 'integer', 'min:1', 'max:120'],
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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_date.after_or_equal' => 'Ngày đi phải từ hôm nay trở đi.',
            'pickup_time.regex'           => 'Giờ đón không hợp lệ.',
            'pickup_time.required'        => 'Vui lòng chọn giờ đón.',
            'pickup_lat.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'pickup_lng.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'dropoff_lat.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'dropoff_lng.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'pickup_detail.required'      => 'Vui lòng chọn điểm đón trên bản đồ.',
            'dropoff_detail.required'     => 'Vui lòng chọn điểm trả trên bản đồ.',
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
