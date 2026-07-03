<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ScheduleTemplate;
use App\Services\BookingPhoneGuardService;
use App\Services\BookingWorkflowService;
use App\Services\DuplicateBookingService;
use App\Services\ReferralCodeService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use App\Services\TripPricingService;
use App\Support\DepartureTimeDisplay;
use App\Support\PageList;
use App\Support\PlatformFees;
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
        private readonly BookingPhoneGuardService $phoneGuard,
        private readonly DuplicateBookingService $duplicateBookings,
    ) {
    }

    public function index(Request $request)
    {
        $this->scheduleLifecycle->sync();

        $offers = PageList::paginateCollection($this->tripListing->listActiveTemplates(), $request);
        $defaultServiceDate = ServiceDate::today();

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
            'defaultServiceDate',
            'prefillReferral',
            'appliedReferral',
            'pendingReferral',
            'referralDiscountMeta',
        ));
    }

    public function checkDuplicateBooking(Request $request)
    {
        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:30'],
        ]);

        $active = $this->duplicateBookings->findActiveBooking($validated['contact_phone']);

        return response()->json([
            'duplicate'      => $active !== null,
            'active_booking' => $active !== null,
            'booking'        => $active ? $this->duplicateBookings->serializeDuplicate($active) : null,
        ]);
    }

    public function quotePrice(Request $request)
    {
        $validated = $request->validate([
            'template_id'     => ['required', 'exists:schedule_templates,id'],
            'pickup_address'  => ['nullable', 'string', 'max:255', SouthernProvinces::inRule()],
            'dropoff_address' => ['nullable', 'string', 'max:255', SouthernProvinces::inRule()],
            'contact_phone'   => ['nullable', 'string', 'max:30'],
        ]);

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        $quote = $this->pricing->quote(
            $template,
            $validated['pickup_address'] ?? null,
            $validated['dropoff_address'] ?? null,
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
            'vehicle_label'             => \App\Support\VehicleDisplay::labelFromVehicle($template->vehicle),
        ]));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_id'      => ['required', 'exists:schedule_templates,id'],
            'service_date'     => ['required', 'date', 'after_or_equal:today'],
            'pickup_time'      => ['nullable', 'string', 'max:8'],
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
        ]);

        if ($validator->fails()) {
            return $this->bookingFormRedirect()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $referralInput = strtoupper(trim((string) ($validated['referral_code'] ?? '')));
        if ($referralInput !== '') {
            $code = $this->referralCodes->resolveUsableCode($referralInput);
            if ($code) {
                session(['guest_referral_code' => $code->code]);
            }
        }

        $template = ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->findOrFail($validated['template_id']);

        $pickupTime = ! empty($validated['pickup_time'])
            ? DepartureTimeDisplay::storageValue($validated['pickup_time'])
            : null;

        $appliedReferral = $this->referralCodes->resolveUsableCode(session('guest_referral_code'));
        $appliedReferralId = $appliedReferral?->id;
        $passengerGender = ($validated['passenger_gender'] ?? 'male') === 'female' ? 'female' : 'male';
        $passengerAge = isset($validated['passenger_age']) ? (int) $validated['passenger_age'] : null;
        $pickupLat = isset($validated['pickup_lat']) ? (float) $validated['pickup_lat'] : null;
        $pickupLng = isset($validated['pickup_lng']) ? (float) $validated['pickup_lng'] : null;

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

        $booking->loadMissing(['schedule', 'referralCode', 'appliedReferralCode']);
        $issuedReferral = $booking->referralCode;

        return redirect()->route('home')->with('booking_success', [
            'trip_code'         => $booking->schedule->shortTripCode(),
            'booking_reference' => $booking->booking_reference,
            'passenger_name'    => $validated['passenger_name'],
            'contact_phone'     => $validated['contact_phone'],
            'referral_code'     => $issuedReferral?->code,
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
        } elseif (str_contains($message, 'SĐT') || str_contains($message, 'điện thoại')) {
            $field = 'contact_phone';
        }

        return $this->bookingFormRedirect()
            ->withErrors([$field => $message])
            ->withInput();
    }
}
