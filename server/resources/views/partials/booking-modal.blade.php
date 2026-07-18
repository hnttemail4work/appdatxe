{{-- Legacy include name: booking flow is now inline (Be-style). --}}
@include('partials.booking-flow', [
    'driverOffers' => $driverOffers ?? collect(),
    'defaultServiceDate' => $defaultServiceDate ?? null,
    'defaultPickupTime' => $defaultPickupTime ?? null,
    'customerBookingPrefill' => $customerBookingPrefill ?? null,
])
