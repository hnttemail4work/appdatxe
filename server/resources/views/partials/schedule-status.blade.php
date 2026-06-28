@php
    $displayStatus = $schedule->displayStatus();
    $statusColor = match($displayStatus) {
        'running'   => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        default     => 'warning text-dark',
    };
    if ($displayStatus === 'scheduled' && $schedule->departure_time <= now()) {
        $statusColor = 'secondary';
    }
@endphp
<span class="badge bg-{{ $statusColor }}">{{ $schedule->statusLabel() }}</span>
