@php
    $statusColor = match($schedule->status) {
        'running'   => 'primary',
        'completed' => 'secondary',
        'cancelled' => 'danger',
        default     => 'warning text-dark',
    };
@endphp
<span class="badge bg-{{ $statusColor }}">{{ $schedule->statusLabel() }}</span>
