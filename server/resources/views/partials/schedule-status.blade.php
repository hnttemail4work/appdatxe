@php
    $displayStatus = $schedule->displayStatus();
    $departedIdle = $displayStatus === 'scheduled' && $schedule->departure_time <= now();
    $statusVariant = \App\Support\StatusBadge::scheduleDisplay($displayStatus, $departedIdle);
@endphp
<span class="status-pill status-pill--{{ $statusVariant }}">{{ $schedule->statusLabel() }}</span>
