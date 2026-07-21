<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelTripRequest;
use App\Http\Requests\Booking\ChangeDropoffRequest;
use App\Http\Requests\Booking\CheckDuplicateBookingRequest;
use App\Http\Requests\Booking\QuotePriceRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\StoreTripReviewRequest;
use App\Http\Requests\Booking\TripStatusRequest;
use App\Models\Booking;
use App\Services\BookingBrowserGuardService;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use App\Services\CustomerWalletService;
use App\Services\DriverAvailabilityService;
use App\Services\DuplicateBookingService;
use App\Services\GuestTripStatusService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\BookingPageSettings;
use App\Support\DepartureTimeDisplay;
use App\Support\ProvinceResolver;
use App\Support\ServiceDate;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use Illuminate\Http\Request;
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
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly CustomerWalletService $customerWallets,
    ) {
    }

    public function index(Request $request)
    {
        return view('booking.index', $this->bookingPageContext($request, true));
    }

    public function driverOffers()
    {
        $this->scheduleLifecycle->sync();

        $offers = $this->tripListing->vehicleTypeCatalog();

        return response()->json([
            'offers' => $offers,
        ]);
    }

    public function trips(Request $request)
    {
        return view('booking.trips', [
            'browserCancelCount' => (int) $request->session()->get('guest_browser_cancel_count', 0),
            'platformHotlinePhone' => (string) config('app.contact_phone'),
        ]);
    }

    public function tripStatus(TripStatusRequest $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();
        $this->scheduleLifecycle->sync();

        $validated = $request->validated();

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

    public function storeTripReview(StoreTripReviewRequest $request)
    {
        $validated = $request->validated();

        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));
        $phone = trim((string) ($validated['contact_phone'] ?? ''));

        $booking = $this->guestTrips->resolve(
            $browserId !== '' ? $browserId : null,
            $phone !== '' ? $phone : null,
            $validated['booking_reference'],
        );

        if (! $booking) {
            $exists = Booking::query()
                ->where('booking_reference', $validated['booking_reference'])
                ->exists();

            return response()->json([
                'message' => $exists
                    ? 'Không xác thực được chuyến đi. Vui lòng tải lại trang Chuyến và thử lại.'
                    : 'Không tìm thấy chuyến đi.',
            ], $exists ? 403 : 404);
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

    public function changeDropoff(ChangeDropoffRequest $request)
    {
        $validated = $request->validated();
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
            $booking = $this->guestTrips->changeDropoff(
                $booking,
                (string) $validated['dropoff_detail'],
                (float) $validated['dropoff_lat'],
                (float) $validated['dropoff_lng'],
                isset($validated['dropoff_address']) ? (string) $validated['dropoff_address'] : null,
                $browserId !== '' ? $browserId : null,
                $phone !== '' ? $phone : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã cập nhật điểm đến và giá mới.',
            'booking' => $this->guestTrips->serialize($booking),
        ]);
    }

    public function previewChangeDropoff(ChangeDropoffRequest $request)
    {
        $validated = $request->validated();
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
            $preview = $this->guestTrips->previewChangeDropoff(
                $booking,
                (string) $validated['dropoff_detail'],
                (float) $validated['dropoff_lat'],
                (float) $validated['dropoff_lng'],
                isset($validated['dropoff_address']) ? (string) $validated['dropoff_address'] : null,
                $browserId !== '' ? $browserId : null,
                $phone !== '' ? $phone : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'dropoff_detail'      => $preview['dropoff_detail'],
            'current_price'       => $preview['current_price'],
            'current_price_label' => $preview['current_price_label'],
            'new_price'           => $preview['new_price'],
            'new_price_label'     => $preview['new_price_label'],
        ]);
    }

    public function cancelTrip(CancelTripRequest $request)
    {
        $validated = $request->validated();

        $browserId = trim((string) ($validated['booking_browser_id'] ?? $request->header('X-Booking-Browser-Id', '')));
        $phone = trim((string) ($validated['contact_phone'] ?? ''));

        if ($browserId === '' && $phone === '') {
            return response()->json(['message' => 'Không xác thực được phiên đặt chuyến.'], 403);
        }

        $booking = $this->guestTrips->resolve(
            $browserId !== '' ? $browserId : null,
            $phone !== '' ? $phone : null,
            $validated['booking_reference'],
        );

        if (! $booking) {
            return response()->json(['message' => 'Không tìm thấy chuyến đi.'], 404);
        }

        if (! $this->guestTrips->guestCanCancel($booking)) {
            return response()->json(['message' => 'Chuyến này không thể hủy.'], 422);
        }

        try {
            $this->workflow->cancelByGuest(
                $booking,
                $phone !== '' ? $phone : null,
                $validated['cancellation_reason_id'] ?? null,
                $validated['cancellation_reason_note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $cancelCount = 0;
        if ($browserId !== '') {
            $cancelCount = $this->browserGuard->recordCancel($browserId);
            $request->session()->put('guest_browser_cancel_count', $cancelCount);
        }

        $booking = $booking->fresh(['schedule.route', 'tripReview']);

        return response()->json([
            'message'        => 'Hủy chuyến thành công.',
            'cancel_count'   => $cancelCount,
            'cancel_blocked' => $browserId !== '' && $this->browserGuard->isCancelBlocked($browserId),
            'block_message'  => $this->browserGuard->blockMessage(),
            'booking'        => $this->guestTrips->serialize($booking),
        ]);
    }

    /** @return array<string, mixed> */
    private function bookingPageContext(Request $request, bool $withDriverOffers): array
    {
        $this->scheduleLifecycle->sync();

        $driverOffers = $withDriverOffers
            ? $this->tripListing->vehicleTypeCatalog()
            : collect();

        $defaultServiceDate = ServiceDate::today();
        $defaultPickupTime = $this->driverAvailability->suggestedPickupClock();

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

        $referralDiscountMeta = $this->referralCodes->discountMeta($appliedReferral);
        $browserCancelCount = (int) $request->session()->get('guest_browser_cancel_count', 0);
        $bookingPageBannerUrl = BookingPageSettings::bannerUrl();
        $customerBookingPrefill = null;
        $customerWalletBalance = null;
        $homeNewsItems = collect();
        $authUser = auth()->user();

        if ($authUser && $authUser->role === 'customer') {
            $customerBookingPrefill = app(\App\Services\CustomerAccountService::class)
                ->bookingPrefill($authUser);
            $customerWalletBalance = $this->customerWallets->balanceFor($authUser);
            $homeNewsItems = app(\App\Services\CustomerInboxService::class)
                ->listFor((int) $authUser->id, \App\Models\CustomerInboxMessage::CATEGORY_INFO, 5);
        } else {
            // Khách chưa đăng nhập: lấy tin tức gần nhất đã broadcast (mẫu theo tiêu đề).
            $homeNewsItems = \App\Models\CustomerInboxMessage::query()
                ->where('category', \App\Models\CustomerInboxMessage::CATEGORY_INFO)
                ->where('meta->type', 'admin_broadcast')
                ->latest('id')
                ->limit(20)
                ->get()
                ->unique('title')
                ->take(5)
                ->values();
        }

        return compact(
            'driverOffers',
            'defaultServiceDate',
            'defaultPickupTime',
            'prefillReferral',
            'appliedReferral',
            'referralDiscountMeta',
            'browserCancelCount',
            'bookingPageBannerUrl',
            'customerBookingPrefill',
            'customerWalletBalance',
            'homeNewsItems',
        );
    }

    public function about()
    {
        return view('pages.about', [
            'platformHotlinePhone' => (string) config('app.contact_phone'),
        ]);
    }

    public function checkDuplicateBooking(CheckDuplicateBookingRequest $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();
        app(\App\Services\DriverTripRequestService::class)->hangOverdueDriverSearches();

        $validated = $request->validated();

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

    public function quotePrice(QuotePriceRequest $request)
    {
        $validated = $request->validated();

        $capacity = (int) $validated['capacity'];
        $vehicleType = $validated['vehicle_type'] ?? null;

        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];
        $dropoffLat = (float) $validated['dropoff_lat'];
        $dropoffLng = (float) $validated['dropoff_lng'];
        $addresses = $this->resolveRouteAddresses($validated, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        $at = null;
        if (! empty($validated['pickup_time'])) {
            try {
                $at = \Carbon\Carbon::parse(
                    \App\Support\DepartureTimeDisplay::storageValue((string) $validated['pickup_time']),
                    config('app.timezone', 'Asia/Ho_Chi_Minh'),
                );
            } catch (\Throwable) {
                $at = now();
            }
        }

        $priceQuote = $this->pricing->quoteForVehicleType(
            $addresses['pickup_address'],
            $addresses['dropoff_address'],
            $capacity,
            $vehicleType,
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
            $at,
        );

        $referral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $discountMeta = $this->referralCodes->discountMeta(
            $referral,
            $validated['contact_phone'] ?? null,
        );
        $discountPercent = $discountMeta['eligible'] ? $discountMeta['percent'] : 0.0;
        $priced = $priceQuote->withReferral($discountPercent);

        return response()->json(array_merge($priced->toApiArray(), [
            'referral_code'             => $discountMeta['code'],
            'referral_discount_label'   => $discountMeta['source_label'],
            'referral_eligible'         => $discountMeta['eligible'] && $discountPercent > 0,
            'referral_attribution_only' => $discountMeta['attribution_only'] ?? false,
            'referral_ineligible_reason'=> $discountMeta['reason'],
            'capacity_label'            => VehicleCapacityOptions::label($capacity),
            'type_label'                => \App\Support\VehicleDisplay::typeLabel($vehicleType),
        ]));
    }

    public function store(StoreBookingRequest $request)
    {
        $this->workflow->expirePastPickupWithoutDriver();

        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer') {
            if ($block = $authUser->bookingBlockMessage()) {
                return $this->bookingFormRedirect()
                    ->withErrors(['booking' => $block])
                    ->withInput();
            }
        }

        $validated = $request->validated();

        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];
        $dropoffLat = (float) $validated['dropoff_lat'];
        $dropoffLng = (float) $validated['dropoff_lng'];

        if ($this->haversineMeters($pickupLat, $pickupLng, $dropoffLat, $dropoffLng) < 200) {
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => 'Không được đặt xe: điểm đi quá gần điểm trả (dưới 200m).'])
                ->withInput();
        }

        $addresses = $this->resolveRouteAddresses($validated, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $validated['pickup_address'] = $addresses['pickup_address'];
        $validated['dropoff_address'] = $addresses['dropoff_address'];
        $scheduleLater = ! empty($validated['service_date']) && ! empty($validated['pickup_time']);
        $validated['service_date'] = $scheduleLater
            ? ServiceDate::parse($validated['service_date'])->toDateString()
            : ServiceDate::today();

        $referralInput = strtoupper(trim((string) ($validated['referral_code'] ?? '')));
        if ($referralInput !== '') {
            $code = $this->referralCodes->resolveUsableCode($referralInput);
            if ($code) {
                session(['guest_referral_code' => $code->code]);
            }
        }

        $template = $this->tripListing->resolveTemplateForCapacity(
            (int) $validated['capacity'],
            $validated['vehicle_type'] ?? null,
        );

        if (! $template) {
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => 'Loại xe không khả dụng, vui lòng chọn loại khác.'])
                ->withInput();
        }

        $pickupTime = $scheduleLater
            ? DepartureTimeDisplay::storageValue((string) $validated['pickup_time'])
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

        if (BookingBrowserGuardService::ENFORCE_CANCEL_BLOCK
            && (int) $request->session()->get('guest_browser_cancel_count', 0) >= BookingBrowserGuardService::CANCEL_BLOCK_LIMIT) {
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

        $authUser = auth()->user();
        $paymentMethod = ($validated['payment_method'] ?? 'cash') === 'wallet' ? 'wallet' : 'cash';
        if ($paymentMethod === 'wallet') {
            if (! $authUser || $authUser->role !== 'customer') {
                return $this->bookingFormRedirect()
                    ->withErrors(['booking' => 'Đăng nhập tài khoản khách để thanh toán bằng ví.'])
                    ->withInput();
            }
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
                autoMatchDriver: true,
            );
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        $paymentUpdates = [
            'payment_method' => $paymentMethod,
        ];
        if ($authUser && $authUser->role === 'customer') {
            $paymentUpdates['customer_id'] = $authUser->id;
        }
        $booking->update($paymentUpdates);

        if ($paymentMethod === 'wallet' && $authUser) {
            try {
                $this->customerWallets->assertCanCoverTrip($authUser, $booking->tripRevenueAmount());
            } catch (InvalidArgumentException $e) {
                try {
                    $booking->update([
                        'booking_status'            => 'cancelled',
                        'trip_status'               => 'cancelled',
                        'payment_status'            => 'unpaid',
                        'cancelled_at'              => now(),
                        'cancelled_by'              => 'system',
                        'cancellation_reason_label' => 'Số dư ví không đủ khi đặt',
                    ]);
                    if ($booking->schedule) {
                        $this->workflow->syncScheduleAvailability($booking->schedule);
                    }
                } catch (\Throwable) {
                }

                return $this->bookingFormError($e);
            }
        }

        session()->forget('guest_referral_code');

        $this->browserGuard->recordActiveBooking($browserId, $booking);

        try {
            $push = app(\App\Services\PushNotificationService::class);
            $push->touchContactPhone($browserId, (string) $validated['contact_phone']);
        } catch (\Throwable) {
        }

        $booking->loadMissing(['schedule', 'appliedReferralCode']);

        $successPayload = [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'booking_reference' => $booking->booking_reference,
            'passenger_name'    => $validated['passenger_name'],
            'contact_phone'     => $validated['contact_phone'],
            'hotline_phone'     => config('app.contact_phone'),
        ];

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

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = static fn (float $deg): float => $deg * M_PI / 180;
        $earth = 6371000.0;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
