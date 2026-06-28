<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\ScheduleTemplate;
use App\Services\BookingWorkflowService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\PlatformFees;
use App\Support\SouthernProvinces;
use App\Support\VehicleCapacityOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class GuestBookingController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly TripListingService $tripListing,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripPricingService $pricing,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $this->tripListing->filtersFromRequest($request);
        $offers = $this->tripListing->query($filters);

        $driverCode = strtoupper(trim((string) $request->query('tx', '')));
        $driverProfile = null;

        if ($driverCode !== '') {
            $driverProfile = DriverProfile::query()
                ->operational()
                ->where('driver_code', $driverCode)
                ->with('user')
                ->first();
        }

        return view('booking.index', compact(
            'offers',
            'filters',
            'driverCode',
            'driverProfile',
        ));
    }

    public function liveSync(Request $request)
    {
        $this->driverRequests->expireStale();

        $filters = $this->tripListing->filtersFromRequest($request);
        $offers = $this->tripListing->query($filters);
        $priceRanges = $this->tripListing->wholeCarPriceRanges($offers);

        return response()->json([
            'synced_at'    => now()->toIso8601String(),
            'service_date' => $filters['service_date'] ?? null,
            'trips'        => $offers->map(fn (ScheduleTemplate $t) => $this->tripListing->serializeOffer(
                $t,
                $filters['service_date'] ?? null,
                $priceRanges[$t->route_id] ?? null,
            ))->values(),
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
            return response()->json(['drivers' => [], 'message' => 'Khung giờ đã qua.'], 422);
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
            'preferred_time'   => ['nullable', 'date_format:H:i'],
            'driver_code'      => ['nullable', 'string', 'max:20'],
            'vehicle_capacity' => ['nullable', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
        ]);

        $template = ScheduleTemplate::query()->with('vehicle')->findOrFail($validated['template_id']);

        $capacity = $template->capacity();
        $driverCode = strtoupper(trim((string) ($validated['driver_code'] ?? '')));
        $guestCapacity = (int) ($validated['vehicle_capacity'] ?? 0);

        if ($driverCode !== '') {
            $profile = DriverProfile::query()
                ->operational()
                ->where('driver_code', $driverCode)
                ->first();

            if ($profile && (int) $profile->vehicle_seats > 0) {
                $capacity = (int) $profile->vehicle_seats;
            }
        } elseif ($guestCapacity > 0) {
            $resolved = $this->resolveTemplateForCapacity($template, $guestCapacity);
            if ($resolved) {
                $template = $resolved;
            }
            $capacity = $guestCapacity;
        }

        return response()->json([
            'occupied_map' => $this->tripListing->occupiedSeatMapForDate(
                $template,
                $validated['service_date'],
                $validated['preferred_time'] ?? null,
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
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        return response()->json($this->pricing->quote(
            $template,
            $validated['trip_type'],
            $validated['pickup_address'] ?? null,
            $validated['dropoff_address'] ?? null,
            $validated['booking_mode'] ?? 'shared',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'service_date'    => ['required', 'date', 'after_or_equal:today'],
            'preferred_time'  => ['nullable', 'date_format:H:i'],
            'passenger_name'  => ['required', 'string', 'max:255'],
            'contact_phone'   => ['required', 'string', 'max:30'],
            'pickup_address'  => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'pickup_detail'   => ['required', 'string', 'max:500'],
            'dropoff_address' => ['required', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_detail'  => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'trip_type'       => ['required', 'in:one_way,round_trip'],
            'booking_mode'    => ['required', 'in:whole_car,shared'],
            'seat_count'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehicle_capacity' => ['nullable', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
            'referral_code'   => ['nullable', 'string', 'max:20'],
            'tx'              => ['nullable', 'string', 'max:20'],
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        if (blank($validated['tx'] ?? null) && ! empty($validated['vehicle_capacity'])) {
            $resolved = $this->resolveTemplateForCapacity($template, (int) $validated['vehicle_capacity']);
            if (! $resolved) {
                return back()
                    ->withErrors(['vehicle_capacity' => 'Chưa có chuyến xe ' . $validated['vehicle_capacity'] . ' chỗ trên tuyến này.'])
                    ->withInput();
            }
            $template = $resolved;
        }

        $preferredTime = trim((string) ($validated['preferred_time'] ?? ''));
        $preferredTime = $preferredTime !== '' ? $preferredTime : null;

        $bookingMode = $validated['booking_mode'];
        $occupiedMap = $this->tripListing->occupiedSeatMapForDate(
            $template,
            $validated['service_date'],
            $preferredTime,
        );
        $capacity = $template->capacity();
        $freeSeats = collect(range(1, $capacity))
            ->map(fn ($n): string => (string) $n)
            ->filter(fn (string $seat): bool => empty($occupiedMap[$seat]))
            ->values()
            ->all();

        if ($bookingMode === 'whole_car') {
            if (count($freeSeats) !== $capacity) {
                return back()
                    ->withErrors(['booking_mode' => 'Đặt cả xe chỉ khả dụng khi chuyến còn trống toàn bộ.'])
                    ->withInput();
            }
            $seatNumbers = $freeSeats;
        } else {
            if ($freeSeats === []) {
                return back()
                    ->withErrors(['booking_mode' => 'Hết chỗ trên chuyến này — vui lòng chọn chuyến khác.'])
                    ->withInput();
            }
            $seatCount = (int) ($validated['seat_count'] ?? 1);
            if ($seatCount < 1) {
                $seatCount = 1;
            }
            if ($seatCount > count($freeSeats)) {
                return back()
                    ->withErrors(['seat_count' => 'Chỉ còn ' . count($freeSeats) . ' ghế trống trên chuyến này.'])
                    ->withInput();
            }
            $seatNumbers = array_slice($freeSeats, 0, $seatCount);
        }

        $referralCode = strtoupper(trim((string) ($validated['referral_code'] ?? '')));
        if ($referralCode !== '') {
            $referrer = DriverProfile::query()
                ->operational()
                ->where('driver_code', $referralCode)
                ->first();

            if (! $referrer) {
                return back()
                    ->withErrors(['referral_code' => 'Không tìm thấy tài xế với mã giới thiệu này.'])
                    ->withInput();
            }
        } else {
            $referralCode = null;
        }

        try {
            $booking = $this->workflow->createBookingFromTemplate(
                $template,
                $validated['contact_phone'],
                $validated['passenger_name'],
                $seatNumbers,
                $validated['service_date'],
                $preferredTime,
                $validated['pickup_address'],
                $validated['pickup_detail'],
                $validated['dropoff_address'],
                $validated['dropoff_detail'] ?? null,
                $validated['notes'] ?? null,
                $validated['trip_type'],
                $referralCode,
                $bookingMode,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['seat_numbers' => $e->getMessage()])->withInput();
        }

        $booking->loadMissing('schedule');

        return redirect()->route('booking.index')->with('booking_success', [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'contact_phone'     => $validated['contact_phone'],
            'awaiting_operator' => true,
        ]);
    }

    private function resolveTemplateForCapacity(ScheduleTemplate $from, int $capacity): ?ScheduleTemplate
    {
        if (! VehicleCapacityOptions::isStandard($capacity)) {
            return null;
        }

        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->where('route_id', $from->route_id)
            ->whereHas('vehicle', fn ($q) => $q->where('capacity', $capacity))
            ->with(['route', 'vehicle'])
            ->first();
    }
}
