<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ScheduleTemplate;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use App\Services\DriverAvailabilityService;
use App\Services\DriverTripRequestService;
use App\Services\GuestTripWatchService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\PlatformFees;
use App\Support\CustomerBookingBanner;
use App\Support\DepartureTimeDisplay;
use App\Support\PageList;
use App\Support\SouthernProvinces;
use App\Support\ServiceDate;
use App\Services\DuplicateBookingService;
use App\Support\VehicleCapacityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class GuestBookingController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly TripListingService $tripListing,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripPricingService $pricing,
        private readonly ReferralCodeService $referralCodes,
        private readonly GuestTripWatchService $tripWatch,
        private readonly DriverTripRequestService $driverRequests,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly BookingPhoneGuardService $phoneGuard,
        private readonly DuplicateBookingService $duplicateBookings,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $this->tripListing->filtersFromRequest($request);
        $offers = PageList::paginateCollection($this->tripListing->query($filters), $request);

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
        $bookingBannerUrl = CustomerBookingBanner::imageUrl();

        if ($flash = session('booking_success')) {
            $ref = trim((string) ($flash['booking_reference'] ?? ''));
            $phone = trim((string) ($flash['contact_phone'] ?? ''));
            if ($ref !== '' && $phone !== '') {
                $this->tripWatch->addToWatchlist($ref, $phone);
            }
        }

        $guestWatchlistCount = $this->tripWatch->watchlistCount();
        $guestActiveOrdersCount = count($this->tripWatch->visibleTrips());
        $vehicleCapacityChoices = VehicleCapacityOptions::choices();

        return view('booking.index', compact(
            'offers',
            'filters',
            'prefillReferral',
            'appliedReferral',
            'pendingReferral',
            'referralDiscountMeta',
            'bookingBannerUrl',
            'guestWatchlistCount',
            'guestActiveOrdersCount',
            'vehicleCapacityChoices',
        ));
    }

    public function checkDuplicateBooking(Request $request)
    {
        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:30'],
            'template_id'   => ['nullable', 'exists:schedule_templates,id'],
        ]);

        $active = $this->duplicateBookings->findActiveBooking($validated['contact_phone']);

        return response()->json([
            'duplicate'      => $active !== null,
            'active_booking' => $active !== null,
            'booking'        => $active ? $this->duplicateBookings->serializeDuplicate($active) : null,
        ]);
    }

    public function liveSync(Request $request)
    {
        $filters = $this->tripListing->filtersFromRequest($request);
        $offers = PageList::paginateCollection($this->tripListing->query($filters), $request);

        return response()->json([
            'synced_at'    => now()->toIso8601String(),
            'service_date' => $filters['service_date'] ?? null,
            'filters'      => [
                'vehicle_capacity' => $filters['vehicle_capacity'] ?? null,
            ],
            'trips'        => $offers->map(fn (ScheduleTemplate $t) => $this->tripListing->serializeOffer(
                $t,
                $filters['service_date'] ?? null,
            ))->values(),
            'pagination'   => [
                'total'        => $offers->total(),
                'current_page' => $offers->currentPage(),
                'last_page'    => $offers->lastPage(),
            ],
        ]);
    }

    public function availableDrivers(Request $request)
    {
        $this->driverRequests->expireStale();

        $validated = $request->validate([
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'service_date'    => ['required', 'date', 'after_or_equal:today'],
            'preferred_time'  => ['nullable', 'date_format:H:i'],
            'pickup_address'  => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_address' => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        $departureTime = $this->driverAvailability->resolveDepartureTime(
            $template,
            $validated['service_date'],
            $validated['preferred_time'] ?? null,
        );

        if (is_string($validated['preferred_time'] ?? null) && trim($validated['preferred_time']) !== '') {
            try {
                $this->driverAvailability->assertPickupTimeAvailable(
                    $validated['service_date'],
                    $validated['preferred_time'],
                );
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'drivers' => [],
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $schedule = \App\Models\Schedule::query()
            ->where('template_id', $template->id)
            ->whereDate('service_date', $validated['service_date'])
            ->where('departure_time', $departureTime)
            ->where('status', 'scheduled')
            ->first();

        $drivers = $this->driverAvailability->availableForBooking(
            $template,
            $validated['service_date'],
            $validated['preferred_time'] ?? null,
            $validated['pickup_address'],
            $validated['dropoff_address'],
            $schedule,
        );

        return response()->json([
            'drivers' => $this->driverAvailability->serializeForGuest($drivers, $schedule),
            'schedule_assigned_driver_id' => $schedule?->driver_id,
            'seats_label' => $schedule ? $schedule->seatsLabel() : null,
        ]);
    }

    public function seatAvailability(Request $request)
    {
        $validated = $request->validate([
            'template_id'      => ['required', 'exists:schedule_templates,id'],
            'service_date'     => ['required', 'date', 'after_or_equal:today'],
            'vehicle_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $template = ScheduleTemplate::query()->with('vehicle')->findOrFail($validated['template_id']);

        $capacity = $template->capacity();

        return response()->json([
            'occupied_map' => $this->tripListing->occupiedSeatMapForDate(
                $template,
                $validated['service_date'],
                null,
            ),
            'capacity'     => $capacity,
        ]);
    }

    public function quotePrice(Request $request)
    {
        $validated = $request->validate([
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'trip_type'       => ['required', 'in:one_way,round_trip'],
            'booking_mode'    => ['nullable', 'in:whole_car,shared'],
            'pickup_address'  => ['nullable', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_address' => ['nullable', 'string', 'max:255', SouthernProvinces::inRule()],
            'seat_count'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehicle_count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'vehicle_capacity' => ['nullable', 'integer', Rule::in(VehicleCapacityOptions::enabled())],
            'contact_phone'   => ['nullable', 'string', 'max:30'],
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        if (! empty($validated['vehicle_capacity'])) {
            try {
                $template = $this->resolveTemplateForCapacity($template, (int) $validated['vehicle_capacity']);
            } catch (InvalidArgumentException) {
                // Giữ template gốc nếu chưa có loại xe — client sẽ báo lỗi
            }
        }

        $bookingMode = $validated['booking_mode'] ?? 'shared';
        $quote = $this->pricing->quote(
            $template,
            $validated['trip_type'],
            $validated['pickup_address'] ?? null,
            $validated['dropoff_address'] ?? null,
            $bookingMode,
        );

        $unitPrice = (int) ($bookingMode === 'whole_car' ? $quote['whole_car_price'] : $quote['seat_price']);
        $seatCount = max((int) ($validated['seat_count'] ?? 1), 1);
        $vehicleCount = $bookingMode === 'whole_car'
            ? max((int) ($validated['vehicle_count'] ?? 1), 1)
            : 1;
        $subtotal = PlatformFees::roundUpToThousand(
            $bookingMode === 'whole_car' ? ($unitPrice * $vehicleCount) : ($unitPrice * $seatCount),
        );
        $referral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $discountMeta = $this->referralCodes->discountMeta(
            $referral,
            $validated['contact_phone'] ?? null,
        );
        $discountPercent = $discountMeta['eligible'] ? $discountMeta['percent'] : 0.0;
        $total = $this->referralCodes->applyDiscount((float) $subtotal, $discountPercent);
        $discountAmount = max(0, $subtotal - (int) $total);

        return response()->json(array_merge($quote, [
            'unit_price'           => $unitPrice,
            'seat_count'           => $seatCount,
            'vehicle_count'        => $vehicleCount,
            'template_id'          => $template->id,
            'vehicle_capacity'     => $template->capacity(),
            'subtotal'             => $subtotal,
            'referral_code'        => $discountMeta['code'],
            'referral_discount_percent' => $discountPercent,
            'referral_discount_amount'  => $discountAmount,
            'referral_eligible'    => $discountMeta['eligible'] && $discountPercent > 0,
            'referral_attribution_only' => $discountMeta['attribution_only'] ?? false,
            'referral_ineligible_reason' => $discountMeta['reason'],
            'total_after_discount' => (int) $total,
        ]));
    }

    public function resolveRoute(Request $request)
    {
        $validated = $request->validate([
            'departure'        => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'destination'      => ['required', 'string', 'max:255', 'different:departure', SouthernProvinces::inRule()],
            'service_date'     => ['nullable', 'date', 'after_or_equal:today'],
            'vehicle_capacity' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'departure.required'    => 'Vui lòng chọn điểm đi.',
            'destination.required'  => 'Vui lòng chọn điểm đến.',
            'destination.different' => 'Điểm đến phải khác điểm đi.',
        ]);

        $serviceDate = $validated['service_date'] ?? ServiceDate::today();
        $vehicleCapacity = isset($validated['vehicle_capacity'])
            ? (int) $validated['vehicle_capacity']
            : null;

        $template = $this->tripListing->resolveTemplateForCustomBooking(
            $validated['departure'],
            $validated['destination'],
            $vehicleCapacity,
        );

        if (! $template) {
            return response()->json([
                'message' => 'Chưa có tuyến cho cặp điểm này — vui lòng gọi tổng đài ' . config('app.contact_phone') . ' để đặt.',
            ], 404);
        }

        return response()->json([
            'trip' => $this->tripListing->serializeOffer($template, $serviceDate),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'service_date'    => ['required', 'date', 'after_or_equal:today'],
            'pickup_time'     => ['nullable', 'string', 'max:20'],
            'passenger_name'  => ['required', 'string', 'max:255'],
            'passenger_gender' => ['nullable', 'in:male,female'],
            'passenger_age'   => ['nullable', 'integer', 'min:1', 'max:120'],
            'contact_phone'   => ['required', 'string', 'max:30'],
            'pickup_address'  => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'pickup_detail'   => ['required', 'string', 'max:500'],
            'pickup_lat'      => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'      => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_detail'  => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'trip_type'       => ['required', 'in:one_way,round_trip'],
            'booking_mode'    => ['required', 'in:whole_car,shared'],
            'seat_count'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehicle_count'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'vehicle_capacity' => ['required', 'integer', Rule::in(VehicleCapacityOptions::enabled())],
        ]);

        if ($validator->fails()) {
            return $this->bookingFormRedirect()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        try {
            $template = $this->resolveTemplateForCapacity($template, (int) $validated['vehicle_capacity']);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        $pickupTimeRaw = trim((string) ($validated['pickup_time'] ?? ''));
        $pickupTime = $pickupTimeRaw !== ''
            ? DepartureTimeDisplay::normalizeForClock($pickupTimeRaw)
            : null;

        try {
            $this->driverAvailability->assertPickupTimeAvailable($validated['service_date'], $pickupTime);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        $bookingMode = $validated['booking_mode'];
        $vehicleCount = $bookingMode === 'whole_car'
            ? max((int) ($validated['vehicle_count'] ?? 1), 1)
            : 1;
        $vehicleCapacity = (int) $validated['vehicle_capacity'];
        $capacity = $template->capacity();

        if ($bookingMode === 'whole_car') {
            $seatNumbers = collect(range(1, $capacity))
                ->map(fn ($n): string => (string) $n)
                ->all();
        } else {
            $occupiedMap = $this->tripListing->occupiedSeatMapForDate(
                $template,
                $validated['service_date'],
                $pickupTime,
            );
            $freeSeats = collect(range(1, $capacity))
                ->map(fn ($n): string => (string) $n)
                ->filter(fn (string $seat): bool => empty($occupiedMap[$seat]))
                ->values()
                ->all();

            if ($freeSeats === []) {
                return $this->bookingFormRedirect()
                    ->withErrors(['booking_mode' => 'Hết chỗ trên chuyến này — vui lòng chọn chuyến khác.'])
                    ->withInput();
            }
            $seatCount = (int) ($validated['seat_count'] ?? 1);
            if ($seatCount < 1) {
                $seatCount = 1;
            }
            if ($seatCount > count($freeSeats)) {
                return $this->bookingFormRedirect()
                    ->withErrors(['seat_count' => 'Chỉ còn ' . count($freeSeats) . ' ghế trống trên chuyến này.'])
                    ->withInput();
            }
            $seatNumbers = array_slice($freeSeats, 0, $seatCount);
        }

        $appliedReferral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $appliedReferralId = $appliedReferral?->id;

        $passengerGender = ($validated['passenger_gender'] ?? 'male') === 'female' ? 'female' : 'male';
        $passengerAge = isset($validated['passenger_age']) ? (int) $validated['passenger_age'] : null;
        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];

        try {
            $this->phoneGuard->assertCanBook($validated['contact_phone']);
        } catch (InvalidArgumentException $e) {
            if ($this->phoneGuard->shouldLogBlockedAttempt($validated['contact_phone'])) {
                try {
                    $blockedSchedule = $this->scheduleLifecycle->resolveScheduleForBooking(
                        $template,
                        $validated['service_date'],
                        $pickupTime,
                    );
                    $this->phoneGuard->logBlockedAttempt(
                        $blockedSchedule,
                        $validated['contact_phone'],
                        $validated['passenger_name'],
                        $validated['pickup_address'],
                        $validated['pickup_detail'],
                    );
                } catch (InvalidArgumentException) {
                    // Bỏ qua nếu không tạo được schedule ghi nhận
                }
            }

            return $this->bookingFormError($e);
        }

        try {
            $booking = $this->workflow->createBookingFromTemplate(
                $template,
                $validated['contact_phone'],
                $validated['passenger_name'],
                $seatNumbers,
                $validated['service_date'],
                $pickupTime,
                $validated['pickup_address'],
                $validated['pickup_detail'],
                $validated['dropoff_address'],
                $validated['dropoff_detail'] ?? null,
                $validated['notes'] ?? null,
                $validated['trip_type'],
                $bookingMode,
                $appliedReferralId,
                $passengerGender,
                $passengerAge,
                $pickupLat,
                $pickupLng,
                $vehicleCount,
                $vehicleCapacity,
            );
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        session()->forget('guest_referral_code');

        $this->tripWatch->addToWatchlist($booking->booking_reference, $validated['contact_phone']);

        $booking->loadMissing(['schedule', 'referralCode', 'appliedReferralCode']);
        $issuedReferral = $booking->referralCode;
        $driverAssigned = (int) ($booking->schedule->driver_id ?? 0) > 0;
        $searchingDriver = ! $driverAssigned;
        $driverDistanceLabel = $booking->driver_pickup_distance_km !== null
            ? \App\Services\DriverProximityService::formatDistanceLabel((float) $booking->driver_pickup_distance_km)
            : null;

        return redirect()->route('home')->with('booking_success', [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'booking_reference' => $booking->booking_reference,
            'contact_phone'     => $validated['contact_phone'],
            'referral_code'     => $issuedReferral?->code,
            'referral_url'      => $issuedReferral ? $issuedReferral->landingUrl() : null,
            'referral_pending'  => true,
            'referral_discount_percent' => PlatformFees::referralCommissionRepeatPercent(),
            'awaiting_operator' => $searchingDriver,
            'driver_assigned'   => $driverAssigned,
            'searching_driver'  => $searchingDriver,
            'driver_distance_label' => $driverDistanceLabel,
        ]);
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
            return $this->bookingFormRedirect()
                ->withErrors(['booking' => 'active_booking'])
                ->withInput();
        } elseif (str_contains($message, 'ghế') || str_contains($message, 'Chuyến không') || str_contains($message, 'Chuyến đã')) {
            $field = 'seat_numbers';
        }

        return $this->bookingFormRedirect()->withErrors([$field => $message])->withInput();
    }

    private function resolveTemplateForCapacity(ScheduleTemplate $template, int $requestedCapacity): ScheduleTemplate
    {
        if ($template->capacity() === $requestedCapacity) {
            return $template;
        }

        $template->loadMissing('route');

        $alt = ScheduleTemplate::query()
            ->where('status', 'active')
            ->where('route_id', $template->route_id)
            ->whereHas('vehicle', fn ($query) => $query->where('capacity', $requestedCapacity))
            ->with(['route', 'vehicle'])
            ->first();

        if (! $alt) {
            throw new InvalidArgumentException(
                'Chưa có chuyến ' . VehicleCapacityOptions::label($requestedCapacity) . ' trên tuyến này — vui lòng chọn loại xe khác.'
            );
        }

        return $alt;
    }
}
