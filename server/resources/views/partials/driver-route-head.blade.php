@php
    $from = $from ?? '';
    $to = $to ?? '';
@endphp
<div class="driver-route-head">
    <div class="driver-route-rail" aria-hidden="true">
        <span class="driver-route-dot"></span>
        <span class="driver-route-line"></span>
        <span class="driver-route-square"></span>
    </div>
    <div class="driver-route-stops">
        <div class="driver-route-from">{{ $from }}</div>
        <div class="driver-route-to">{{ $to }}</div>
    </div>
</div>
