@php
    /** @var \App\Models\DriverProfile|null $profile */
    $profile = $profile ?? null;
    $distanceLabel = $distanceLabel ?? null;
    $compact = (bool) ($compact ?? false);
@endphp

@if($profile)
    @php
        $user = $profile->user;
        $name = $user->name ?? '—';
        $initial = mb_substr($name, 0, 1);
        $portraitUrl = $profile->photoUrl('photo_portrait');
        $vehicleUrl = $profile->firstVehiclePhotoUrl();
    @endphp
    <div class="booking-driver-brief{{ $compact ? ' booking-driver-brief--compact' : '' }}">
        <div class="booking-driver-brief-identity">
            @if($portraitUrl)
                <img src="{{ $portraitUrl }}" alt="" class="booking-driver-brief-avatar rounded-circle object-fit-cover border" loading="lazy" decoding="async">
            @else
                <div class="booking-driver-brief-avatar booking-driver-brief-avatar-fallback rounded-circle">{{ $initial }}</div>
            @endif
            <div class="booking-driver-brief-meta">
                <div class="booking-driver-brief-name">{{ $name }}</div>
                @if($profile->driver_code)
                    <div class="cell-muted small">{{ $profile->driver_code }}</div>
                @endif
                @if($distanceLabel)
                    <div class="cell-muted small">{{ $distanceLabel }}</div>
                @endif
            </div>
        </div>
        @if($vehicleUrl)
            <a href="{{ $vehicleUrl }}" target="_blank" rel="noopener" class="booking-driver-brief-vehicle" title="Ảnh xe">
                <img src="{{ $vehicleUrl }}" alt="Xe {{ $profile->vehicle_license_plate ?? '' }}" class="booking-driver-brief-vehicle-photo" loading="lazy" decoding="async">
                @if($profile->vehicle_license_plate)
                    <span class="booking-driver-brief-vehicle-plate">{{ $profile->vehicle_license_plate }}</span>
                @endif
            </a>
        @elseif($profile->vehicle_license_plate)
            <div class="booking-driver-brief-vehicle booking-driver-brief-vehicle--text">
                <span class="booking-driver-brief-vehicle-plate">{{ $profile->vehicle_license_plate }}</span>
            </div>
        @endif
    </div>
@endif
