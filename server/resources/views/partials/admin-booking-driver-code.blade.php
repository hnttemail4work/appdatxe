@php
/** @var \App\Models\DriverProfile|null $profile */
$profile = $profile ?? null;
@endphp

@if($profile?->driver_code)
    <span class="driver-meta-code admin-booking-driver-code">{{ $profile->driver_code }}</span>
@else
    <span class="text-muted">—</span>
@endif
