@php
/** @var array<string, mixed> $passenger */
@endphp
<div class="driver-passenger-item{{ ($isLast ?? false) ? '' : ' driver-passenger-item--split' }}">
    <div class="driver-passenger-head">
        <strong>{{ $passenger['passenger_name'] ?? 'Hành khách' }}</strong>
    </div>
    @if(! empty($passenger['passenger_profile']))
        <div class="driver-info-line">{{ $passenger['passenger_profile'] }}</div>
    @endif
    @if(! empty($passenger['pickup']) || ! empty($passenger['pickup_time']))
        <div class="driver-info-line">
            <span class="driver-info-k">Đón</span>
            {{ $passenger['pickup_time'] ?? '—' }} · {{ $passenger['pickup'] ?? '—' }}
        </div>
    @endif
    @if(! empty($passenger['dropoff']))
        <div class="driver-info-line"><span class="driver-info-k">Trả</span> {{ $passenger['dropoff'] }}</div>
    @endif
    @if(! empty($passenger['notes']))
        <div class="driver-info-line driver-info-line--note">{{ $passenger['notes'] }}</div>
    @endif
</div>
