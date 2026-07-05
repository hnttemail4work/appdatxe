<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Services\BookingBrowserGuardService;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use App\Services\DuplicateBookingService;
use App\Services\GuestTripStatusService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\BookingPageSettings;
use App\Support\DeparturePlan;
use App\Support\DepartureTimeDisplay;
use App\Support\ProvinceResolver;
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
        private readonly GuestTripStatusService $guestTrips,
    ) {
    }

    public function index(Request $request)
    {
        return view('booking.index', $this->bookingPageContext($request, true));
    }

    public function trips(Request $request)
    {
        $bookingReferralSuccess = session('booking_success.referral_code')
            ? [
                'code' => session('booking_success.referral_code'),
                'url' => session('booking_success.referral_url')
                    ?: route('home', ['ref' => session('booking_success.referral_code')]),
                'discount_percent' => session('booking_success.referral_discount_percent'),
                'pending' => session('booking_success.referral_pending', true),
            ]
            : null;

        return view('booking.trips', [
            'browserCancelCount' => (int) $request->session()->get('guest_browser_cancel_count', 0),
            'platformHotlinePhone' => (string) config('app.contact_phone'),
            'bookingReferralSuccess' => $bookingReferralSuccess,
        ]);
    }

    public function tripStatus(Request $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();
        $this->scheduleLifecycle->sync();

        $validated = $request->validate([
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
            'booking_reference'  => ['nullable', 'string', 'max:64'],
        ]);

        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));
        $phone = trim((string) ($validated['contact_phone'] ?? ''));
        $bookingReference = trim((string) ($validated['booking_reference'] ?? ''));

        if ($browserId === '' && $phone === '' && $bookingReference === '') {
            return response()->json(['message' => 'Thiếu thông tin kiểm tra.'], 422);
        }

        $booking = $this->guestTrips->resolve(
            $browserId !== '' ? $browserId : null,
            $phone !== '' ? $phone : null,
            $bookingReference !== '' ? $bookingReference : null,
        );

        if (! $booking) {
            return response()->json([
                'has_trip' => false,
                'booking'  => null,
            ]);
        }

        return response()->json([
            'has_trip' => true,
            'booking'  => $this->guestTrips->serialize($booking),
        ]);
    }

    public function storeTripReview(Request $request)
    {
        $validated = $request->validate([
            'booking_reference'  => ['required', 'string', 'max:64'],
            'sentiment'          => ['required', 'in:like,dislike'],
            'comment'            => ['nullable', 'string', 'max:500'],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'booking_browser_id' => ['nullable', 'string', 'max:128'],
        ]);

        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));
        $phone = trim((string) ($validated['contact_phone'] ?? ''));

        $booking = $this->guestTrips->resolve(
            $browserId !== '' ? $browserId : null,
            $phone !== '' ? $phone : null,
            $validated['booking_reference'],
        );

        if (! $booking) {
            return response()->json(['message' => 'Không tìm thấy chuyến đi.'], 404);
        }

        try {
            $this->guestTrips->storeReview(
                $booking,
                $validated['sentiment'],
                $validated['comment'] ?? null,
                $browserId !== '' ? $browserId : null,
                $phone !== '' ? $phone : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $booking = $booking->fresh(['schedule.route', 'tripReview']);

        return response()->json([
            'message' => 'Cảm ơn bạn đã đánh giá!',
            'booking' => $this->guestTrips->serialize($booking),
        ]);
    }

    /** @return array<string, mixed> */
    private function bookingPageContext(Request $request, bool $withDriverOffers): array
    {
        $this->scheduleLifecycle->sync();

        $driverOffers = $withDriverOffers
            ? $this->tripListing->listBookableOffers()
                ->map(fn ($profile) => $this->tripListing->serializeOffer($profile))
                ->values()
            : collect();

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
        $bookingPageHeroTitle = BookingPageSettings::heroTitle();
        $bookingPageBannerUrl = BookingPageSettings::bannerUrl();

        return compact(
            'driverOffers',
            'defaultServiceDate',
            'defaultPickupTime',
            'prefillReferral',
            'appliedReferral',
            'pendingReferral',
            'referralDiscountMeta',
            'browserCancelCount',
            'bookingPageHeroTitle',
            'bookingPageBannerUrl',
        );
    }

    public function about()
    {
        return view('pages.about', [
            'platformHotlinePhone' => (string) config('app.contact_phone'),
        ]);
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
            'pickup_address'     => ['nullable', 'string', 'max:255'],
            'dropoff_address'    => ['nullable', 'string', 'max:255'],
            'pickup_detail'      => ['required', 'string', 'max:500'],
            'dropoff_detail'     => ['required', 'string', 'max:500'],
            'pickup_lat'         => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'         => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat'        => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'        => ['required', 'numeric', 'between:-180,180'],
            'departure_plan'     => ['nullable', 'string', 'in:today,tomorrow,later'],
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

        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];
        $dropoffLat = (float) $validated['dropoff_lat'];
        $dropoffLng = (float) $validated['dropoff_lng'];
        $addresses = $this->resolveRouteAddresses($validated, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $departurePlan = DeparturePlan::normalize($validated['departure_plan'] ?? DeparturePlan::TODAY);

        $quote = $this->pricing->quote(
            $template,
            $addresses['pickup_address'],
            $addresses['dropoff_address'],
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
            $departurePlan,
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
            'service_date'     => ['nullable', 'date', 'after_or_equal:today'],
            'departure_plan'   => ['required', 'string', 'in:today,tomorrow,later'],
            'pickup_time'      => ['nullable', 'string', 'max:8', 'regex:/^\d{1,2}:\d{2}$/'],
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
        ], [
            'service_date.after_or_equal' => 'Ngày đi phải từ hôm nay trở đi.',
            'pickup_time.regex'           => 'Giờ đón không hợp lệ.',
            'pickup_lat.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'pickup_lng.required'         => 'Vui lòng ghim điểm đón trên bản đồ.',
            'dropoff_lat.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'dropoff_lng.required'        => 'Vui lòng ghim điểm trả trên bản đồ.',
            'pickup_detail.required'      => 'Vui lòng chọn điểm đón trên bản đồ.',
            'dropoff_detail.required'     => 'Vui lòng chọn điểm trả trên bản đồ.',
        ]);

        if ($validator->fails()) {
            return $this->bookingFormRedirect()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];
        $dropoffLat = (float) $validated['dropoff_lat'];
        $dropoffLng = (float) $validated['dropoff_lng'];
        $addresses = $this->resolveRouteAddresses($validated, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $validated['pickup_address'] = $addresses['pickup_address'];
        $validated['dropoff_address'] = $addresses['dropoff_address'];
        $departurePlan = DeparturePlan::normalize($validated['departure_plan']);
        $validated['service_date'] = DeparturePlan::resolveServiceDate(
            $departurePlan,
            $validated['service_date'] ?? null,
        );

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
                $dropoffLat,
                $dropoffLng,
                $departurePlan,
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
            'hotline_phone'     => config('app.contact_phone'),
            'referral_code'     => $issuedReferral?->code,
        ];

        if ($issuedReferral) {
            $successPayload['referral_url'] = $issuedReferral->landingUrl();
            $successPayload['referral_discount_percent'] = $issuedReferral->customerDiscountPercent();
            $successPayload['referral_pending'] = $issuedReferral->status === \App\Models\ReferralCode::STATUS_PENDING;
        }

        return redirect()->route('booking.trips')->with('booking_success', $successPayload);
    }

    /** @return array{pickup_address: string, dropoff_address: string} */
    private function resolveRouteAddresses(
        array $input,
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
    ): array {
        $pickup = trim((string) ($input['pickup_address'] ?? ''));
        $dropoff = trim((string) ($input['dropoff_address'] ?? ''));
        $pickupDetail = trim((string) ($input['pickup_detail'] ?? ''));
        $dropoffDetail = trim((string) ($input['dropoff_detail'] ?? ''));

        if ($pickup === '') {
            $pickup = ProvinceResolver::fromMapPick($pickupLat, $pickupLng, $pickupDetail !== '' ? $pickupDetail : null)
                ?? 'Khác';
        }

        if ($dropoff === '') {
            $dropoff = ProvinceResolver::fromMapPick($dropoffLat, $dropoffLng, $dropoffDetail !== '' ? $dropoffDetail : null)
                ?? 'Khác';
        }

        return [
            'pickup_address'  => $pickup,
            'dropoff_address' => $dropoff,
        ];
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
