<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ScheduleTemplate;
use App\Services\BookingWorkflowService;
use App\Services\DriverAvailabilityService;
use App\Services\DriverTripRequestService;
use App\Services\GuestTripWatchService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\DepartureTimeDisplay;
use App\Support\PlatformFees;
use App\Support\PageList;
use App\Support\SouthernProvinces;
use App\Support\ServiceDate;
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
        private readonly GuestTripWatchService $tripWatch,
        private readonly DriverTripRequestService $driverRequests,
        private readonly DriverAvailabilityService $driverAvailability,
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

        return view('booking.index', compact(
            'offers',
            'filters',
            'prefillReferral',
            'appliedReferral',
            'pendingReferral',
            'referralDiscountMeta',
        ));
    }

    public function liveSync(Request $request)
    {
        $filters = $this->tripListing->filtersFromRequest($request);
        $offers = PageList::paginateCollection($this->tripListing->query($filters), $request);

        return response()->json([
            'synced_at'    => now()->toIso8601String(),
            'service_date' => $filters['service_date'] ?? null,
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

        if ($departureTime <= now()) {
            return response()->json([
                'drivers' => [],
                'message' => 'Giờ đón phải sau thời gian hiện tại.',
            ], 422);
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
            'contact_phone'   => ['nullable', 'string', 'max:30'],
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

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
        $subtotal = $bookingMode === 'whole_car' ? $unitPrice : $unitPrice * $seatCount;

        $referral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $discountMeta = $this->referralCodes->discountMeta(
            $referral,
            $validated['contact_phone'] ?? null,
        );
        $discountPercent = $discountMeta['eligible'] ? $discountMeta['percent'] : 0.0;
        $total = $this->referralCodes->applyDiscount((float) $subtotal, $discountPercent);
        $discountAmount = (int) round($subtotal - $total, 0);

        return response()->json(array_merge($quote, [
            'unit_price'           => $unitPrice,
            'seat_count'           => $seatCount,
            'subtotal'             => $subtotal,
            'referral_code'        => $discountMeta['code'],
            'referral_discount_percent' => $discountPercent,
            'referral_discount_amount'  => $discountAmount,
            'referral_eligible'    => $discountMeta['eligible'] && $discountPercent > 0,
            'referral_attribution_only' => $discountMeta['attribution_only'] ?? false,
            'referral_ineligible_reason' => $discountMeta['reason'],
            'total_after_discount' => (int) round($total, 0),
        ]));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'service_date'    => ['required', 'date', 'after_or_equal:today'],
            'pickup_time'     => ['required', 'string', 'max:20'],
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
            'vehicle_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
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

        $pickupTime = DepartureTimeDisplay::normalizeForClock($validated['pickup_time']);

        try {
            $this->driverAvailability->assertPickupTimeAvailable($validated['service_date'], $pickupTime);
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        $bookingMode = $validated['booking_mode'];
        $occupiedMap = $this->tripListing->occupiedSeatMapForDate(
            $template,
            $validated['service_date'],
            $pickupTime,
        );
        $capacity = $template->capacity();
        $freeSeats = collect(range(1, $capacity))
            ->map(fn ($n): string => (string) $n)
            ->filter(fn (string $seat): bool => empty($occupiedMap[$seat]))
            ->values()
            ->all();

        if ($bookingMode === 'whole_car') {
            if (count($freeSeats) !== $capacity) {
                return $this->bookingFormRedirect()
                    ->withErrors(['booking_mode' => 'Đặt cả xe chỉ khả dụng khi chuyến còn trống toàn bộ.'])
                    ->withInput();
            }
            $seatNumbers = $freeSeats;
        } else {
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
            );
        } catch (InvalidArgumentException $e) {
            return $this->bookingFormError($e);
        }

        session()->forget('guest_referral_code');

        $this->tripWatch->addToWatchlist($booking->booking_reference, $validated['contact_phone']);

        $booking->loadMissing(['schedule', 'referralCode', 'appliedReferralCode']);
        $issuedReferral = $booking->referralCode;
        $driverAssigned = (int) ($booking->schedule->driver_id ?? 0) > 0;

        return redirect()->route('home')->with('booking_success', [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'booking_reference' => $booking->booking_reference,
            'contact_phone'     => $validated['contact_phone'],
            'referral_code'     => $issuedReferral?->code,
            'referral_url'      => $issuedReferral ? $issuedReferral->landingUrl() : null,
            'referral_pending'  => true,
            'referral_discount_percent' => PlatformFees::referralCommissionRepeatPercent(),
            'awaiting_operator' => ! $driverAssigned,
            'driver_assigned'   => $driverAssigned,
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
        } elseif (str_contains($message, 'ghế') || str_contains($message, 'Chuyến không') || str_contains($message, 'Chuyến đã')) {
            $field = 'seat_numbers';
        }

        return $this->bookingFormRedirect()->withErrors([$field => $message])->withInput();
    }
}
