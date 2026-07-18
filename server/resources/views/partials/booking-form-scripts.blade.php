<script>
window.__bookingCheckDuplicateUrl = @json(route('booking.checkDuplicate'));
window.__bookingDriverOffersUrl = @json(route('booking.driverOffers'));
window.__quotePriceUrl = @json(route('booking.quotePrice'));
@include('partials.geocode-client-config')
window.__bookingRestoreModal = @json($bookingRestoreModal ?? null);
window.__defaultServiceDate = @json($defaultServiceDate ?? now()->toDateString());
window.__todayDate = @json(now()->toDateString());
window.__defaultPickupTime = @json($defaultPickupTime ?? app(\App\Services\DriverAvailabilityService::class)->suggestedPickupClock());
window.__pickupLeadMinutes = @json(\App\Services\DriverAvailabilityService::MIN_PICKUP_LEAD_MINUTES);
window.__referralDiscountPercent = @json($referralDiscountMeta['percent'] ?? 0);
window.__referralDiscountLabel = @json($referralDiscountMeta['source_label'] ?? (($appliedReferral ?? null)?->customerDiscountSourceLabel()));
window.__referralHasCode = @json((bool) ($appliedReferral ?? null));
window.__bookingSuccess = @json(session('booking_success'));
window.__appContactPhone = @json(config('app.contact_phone'));
window.__guestBrowserCancelCount = @json((int) ($browserCancelCount ?? session('guest_browser_cancel_count', 0)));
window.__guestBrowserCancelBlockLimit = @json(\App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT);
window.__customerBookingPrefill = @json($customerBookingPrefill ?? null);
</script>
<script src="{{ asset('js/booking-browser-guard.js') }}?v={{ filemtime(public_path('js/booking-browser-guard.js')) }}"></script>
<script src="{{ asset('js/idle-poll.js') }}?v={{ filemtime(public_path('js/idle-poll.js')) }}"></script>
<script src="{{ asset('js/booking-active-session.js') }}?v={{ filemtime(public_path('js/booking-active-session.js')) }}"></script>
<script src="{{ asset('js/geocode-search-ui.js') }}?v={{ filemtime(public_path('js/geocode-search-ui.js')) }}"></script>
<script src="{{ asset('js/geocode-resolve.js') }}?v={{ filemtime(public_path('js/geocode-resolve.js')) }}"></script>
<script src="{{ asset('js/customer-booking.js') }}?v={{ filemtime(public_path('js/customer-booking.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/booking-catalog-sync.js') }}?v={{ filemtime(public_path('js/booking-catalog-sync.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
