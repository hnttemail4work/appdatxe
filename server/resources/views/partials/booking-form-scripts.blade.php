<script>
window.__bookingCheckDuplicateUrl = @json(route('booking.checkDuplicate'));
window.__quotePriceUrl = @json(route('booking.quotePrice'));
window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
window.__bookingTemplates = @json($bookingTemplates ?? collect());
window.__bookingRestoreModal = @json($bookingRestoreModal ?? null);
window.__defaultServiceDate = @json($defaultServiceDate ?? now()->toDateString());
window.__defaultPickupTime = @json($defaultPickupTime ?? now()->addHour()->format('H:i'));
window.__referralDiscountPercent = @json($referralDiscountMeta['percent'] ?? 0);
window.__referralHasCode = @json((bool) ($appliedReferral ?? null));
window.__bookingReferralSuccess = @json($bookingReferralSuccess ?? null);
window.__bookingSuccess = @json(session('booking_success'));
window.__appContactPhone = @json(config('app.contact_phone'));
window.__guestBrowserCancelCount = @json((int) ($browserCancelCount ?? session('guest_browser_cancel_count', 0)));
window.__guestBrowserCancelBlockLimit = @json(\App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT);
</script>
<script src="{{ asset('js/booking-browser-guard.js') }}?v={{ filemtime(public_path('js/booking-browser-guard.js')) }}"></script>
<script src="{{ asset('js/booking-active-session.js') }}?v={{ filemtime(public_path('js/booking-active-session.js')) }}"></script>
<script src="{{ asset('js/customer-booking.js') }}?v={{ filemtime(public_path('js/customer-booking.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
