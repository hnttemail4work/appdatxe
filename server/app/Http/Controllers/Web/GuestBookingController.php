<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Services\BookingBrowserGuardService;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use App\Services\DuplicateBookingService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\DepartureTimeDisplay;
use App\Support\SouthernProvinces;
use App\Support\ServiceDate;
use App\Support\VehicleCapacityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class GuestBookingController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly TripListingService $tripListing,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripPricingService $pricing,
        private readonly ReferralCodeService $referralCodes,
        private readonly BookingPhoneGuardService $phoneGuard,
        private readonly DuplicateBookingService $duplicateBookings,
        private readonly BookingBrowserGuardService $browserGuard,
    ) {
    }

    public function index(Request $request)
    {
        $this->scheduleLifecycle->sync();

        $driverOffers = $this->tripListing->listBookableOffers()
            ->map(fn ($profile) => $this->tripListing->serializeOffer($profile))
            ->values();
        $defaultPickupAt = now()->addHour();
        $defaultServiceDate = $defaultPickupAt->toDateString();
        $defaultPickupTime = $defaultPickupAt->format('H:i');

        $prefillReferral = strtoupper(trim((string) $request->query('ref', '')));
        $appliedReferral = $prefillReferral !== ''
            ? $this->referralCodes->resolveUsableCode($prefillReferral)
            : null;

        if ($request->has('ref')) {
            if ($appliedReferral) {
                session(['guest_referral_code' => $appliedReferral->code]);
            } else {
                session()->forget('guest_referral_code');
            }
        } elseif (session()->has('guest_referral_code')) {
            $appliedReferral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
            if (! $appliedReferral) {
                session()->forget('guest_referral_code');
            }
        }

        $pendingReferral = null;
        if ($prefillReferral !== '' && ! $appliedReferral) {
            $pendingReferral = ReferralCode::query()
                ->where('code', $prefillReferral)
                ->where('status', ReferralCode::STATUS_PENDING)
                ->first();
        }

        $referralDiscountMeta = $this->referralCodes->discountMeta($appliedReferral);
        $browserCancelCount = (int) $request->session()->get('guest_browser_cancel_count', 0);

        return view('booking.index', compact(
            'driverOffers',
            'defaultServiceDate',
            'defaultPickupTime',
            'prefillReferral',
            'appliedReferral',
            'pendingReferral',
            'referralDiscountMeta',
            'browserCancelCount',
        ));
    }

    public function checkDuplicateBooking(Request $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();

        $validated = $request->validate([
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
        ]);

        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));
        $phone = trim((string) ($validated['contact_phone'] ?? ''));

        if ($browserId === '' && $phone === '') {
            return response()->json(['message' => 'Thiếu thông tin kiểm tra.'], 422);
        }

        if ($browserId !== '') {
            if ($this->browserGuard->isCancelBlocked($browserId)) {
                return response()->json([
                    'duplicate'      => true,
                    'reason'         => 'browser_cancel',
                    'active_booking' => false,
                    'message'        => $this->browserGuard->blockMessage(),
                ]);
            }

            $browserActive = $this->browserGuard->findActiveBooking($browserId);

            if ($browserActive) {
                return response()->json([
                    'duplicate'      => true,
                    'reason'         => 'browser',
                    'active_booking' => true,
                    'message'        => $this->browserGuard->activeBookingBlockMessage(),
                    'booking'        => $this->duplicateBookings->serializeDuplicate($browserActive),
                ]);
            }
        }

        if ($phone !== '') {
            $phoneActive = $this->duplicateBookings->findActiveBooking($phone);

            if ($phoneActive) {
                return response()->json([
                    'duplicate'      => true,
                    'reason'         => 'phone',
                    'active_booking' => true,
                    'booking'        => $this->duplicateBookings->serializeDuplicate($phoneActive),
                ]);
            }
        }

        return response()->json([
            'duplicate'      => false,
            'active_booking' => false,
            'booking'        => null,
        ]);
    }

    public function quotePrice(Request $request)
    {
        $validated = $request->validate([
            'template_id'        => ['nullable', 'exists:schedule_templates,id'],
            'vehicle_id'         => ['nullable', 'exists:vehicles,id'],
            'driver_profile_id'  => ['nullable', 'exists:driver_profiles,id'],
            'pickup_address'     => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_address'    => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
        ]);

        if (empty($validated['template_id']) && empty($validated['vehicle_id']) && empty($validated['driver_profile_id'])) {
            return response()->json(['message' => 'Thiếu thông tin tài xế.'], 422);
        }

        $template = $this->tripListing->resolveTemplate(
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
            isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
            isset($validated['template_id']) ? (int) $validated['template_id'] : null,
        );

        if (! $template || ! $template->vehicle) {
            return response()->json(['message' => 'Xe không khả dụng.'], 422);
        }

        $quote = $this->pricing->quote(
            $template,
            $validated['pickup_address'],
            $validated['dropoff_address'],
        );

        $subtotal = (int) $quote['whole_car_price'];
        $referral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $discountMeta = $this->referralCodes->discountMeta(
            $referral,
            $validated['contact_phone'] ?? null,
        );
        $discountPercent = $discountMeta['eligible'] ? $discountMeta['percent'] : 0.0;
        $total = $this->referralCodes->applyDiscount((float) $subtotal, $discountPercent);
        $discountAmount = max(0, $subtotal - (int) $total);

        return response()->json(array_merge($quote, [
            'subtotal'                  => $subtotal,
            'referral_code'             => $discountMeta['code'],
            'referral_discount_percent' => $discountPercent,
            'referral_discount_amount'  => $discountAmount,
            'referral_eligible'         => $discountMeta['eligible'] && $discountPercent > 0,
            'referral_attribution_only' => $discountMeta['attribution_only'] ?? false,
            'referral_ineligible_reason'=> $discountMeta['reason'],
            'total_after_discount'      => (int) $total,
            'template_id'               => $template->id,
            'vehicle_id'                => $template->vehicle_id,
            'vehicle_label'             => \App\Support\VehicleDisplay::labelFromVehicle($template->vehicle),
            'license_plate'             => $template->vehicle->license_plate,
            'capacity_label'            => VehicleCapacityOptions::label((int) $template->vehicle->capacity),
        ]));
    }

    public function store(Request $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();

        $validator = Validator::make($request->all(), [
            'template_id'       => ['nullable', 'exists:schedule_templates,id'],
            'vehicle_id'        => ['nullable', 'exists:vehicles,id'],
            'driver_profile_id' => ['nullable', 'exists:driver_profiles,id'],
            'service_date'     => ['required', 'date', 'after_or_equal:today'],
            'pickup_time'      => ['required', 'string', 'max:8', 'regex:/^\d{1,2}:\d{2}$/'],
            'passenger_name'   => ['required', 'string', 'max:255'],
            'passenger_gender' => ['nullable', 'in:male,female'],
            'passenger_age'    => ['nullable', 'integer', 'min:1', 'max:120'],
            'contact_phone'    => ['required', 'string', 'max:30'],
            'pickup_address'   => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_address'  => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'pickup_detail'    => ['nullable', 'string', 'max:500'],
            'dropoff_detail'   => ['nullable', 'string', 'max:500'],
            'pickup_lat'       => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_lng'       => ['nullable', 'numeric', 'between:-180,180'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'referral_code'    => ['nullable', 'string', 'max:32'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
        ], [
            'service_date.required'       => 'Vui lòng chọn ngày đi.',
            'service_date.after_or_equal' => 'Ngày đi phải từ hôm nay trở đi.',
            'pickup_time.required'        => 'Vui lòng chọn giờ đón.',
            'pickup_time.regex'           => 'Giờ đón không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return $this->bookingFormRedirect()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        if (empty($validated['template_id']) && empty($validated['vehicle_id']) && empty($validated['driver_profile_id'])) {
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => 'Vui lòng chọn tài xế.'])
                ->withInput();
        }

        $referralInput = strtoupper(trim((string) ($validated['referral_code'] ?? '')));
        if ($referralInput !== '') {
            $code = $this->referralCodes->resolveUsableCode($referralInput);
            if ($code) {
                session(['guest_referral_code' => $code->code]);
            }
        }

        $template = $this->tripListing->resolveTemplate(
            isset($validated['driver_profile_id']) ? (int) $validated['driver_profile_id'] : null,
            isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
            isset($validated['template_id']) ? (int) $validated['template_id'] : null,
        );

        if (! $template) {
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => 'Tài xế không khả dụng.'])
                ->withInput();
        }

        $pickupTime = ! empty($validated['pickup_time'])
            ? DepartureTimeDisplay::storageValue($validated['pickup_time'])
            : null;

        $appliedReferral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $appliedReferralId = $appliedReferral?->id;
        $passengerGender = ($validated['passenger_gender'] ?? 'male') === 'female' ? 'female' : 'male';
        $passengerAge = isset($validated['passenger_age']) ? (int) $validated['passenger_age'] : null;
        $pickupLat = isset($validated['pickup_lat']) ? (float) $validated['pickup_lat'] : null;
        $pickupLng = isset($validated['pickup_lng']) ? (float) $validated['pickup_lng'] : null;
        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));

        try {
            $this->browserGuard->assertCanBook($browserId !== '' ? $browserId : null);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        if ((int) $request->session()->get('guest_browser_cancel_count', 0) >= BookingBrowserGuardService::CANCEL_BLOCK_LIMIT) {
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => $this->browserGuard->blockMessage()])
                ->withInput();
        }

        try {
            $this->duplicateBookings->assertCanBook($validated['contact_phone']);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        try {
            $this->phoneGuard->assertCanBook($validated['contact_phone']);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        try {
            $booking = $this->workflow->createBookingFromTemplate(
                $template,
                $validated['contact_phone'],
                $validated['passenger_name'],
                $validated['service_date'],
                $pickupTime,
                $validated['pickup_address'],
                $validated['pickup_detail'] ?? null,
                $validated['dropoff_address'],
                $validated['dropoff_detail'] ?? null,
                $validated['notes'] ?? null,
                $appliedReferralId,
                $passengerGender,
                $passengerAge,
                $pickupLat,
                $pickupLng,
            );
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        session()->forget('guest_referral_code');

        $this->browserGuard->recordActiveBooking($browserId, $booking);

        $booking->loadMissing(['schedule', 'referralCode', 'appliedReferralCode']);
        $issuedReferral = $booking->referralCode;

        $successPayload = [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'booking_reference' => $booking->booking_reference,
            'passenger_name'    => $validated['passenger_name'],
            'contact_phone'     => $validated['contact_phone'],
            'referral_code'     => $issuedReferral?->code,
        ];

        if ($issuedReferral) {
            $successPayload['referral_url'] = $issuedReferral->landingUrl();
            $successPayload['referral_discount_percent'] = $issuedReferral->customerDiscountPercent();
            $successPayload['referral_pending'] = $issuedReferral->status === \App\Models\ReferralCode::STATUS_PENDING;
        }

        return redirect()->route('home')->with('booking_success', $successPayload);
    }

    private function bookingFormRedirect(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('home');
    }

    private function bookingFormError(InvalidArgumentException $e): \Illuminate\Http\RedirectResponse
    {
        $message = $e->getMessage();
        $field = 'booking';

        if (str_contains($message, 'Giờ đón') || str_contains($message, 'giờ đón')) {
            $field = 'pickup_time';
        } elseif (str_contains($message, 'khởi hành')) {
            $field = 'service_date';
        } elseif (str_contains($message, 'cuốc chưa hoàn thành')) {
            $field = 'booking';
        } elseif (str_contains($message, 'phiên trình duyệt')) {
            $field = 'booking';
        } elseif (str_contains($message, 'SĐT') || str_contains($message, 'điện thoại')) {
            $field = 'contact_phone';
        }

        return $this->bookingFormRedirect()
            ->withErrors([$field => $message])
            ->withInput();
    }
}
