<?php
    $statusColor = match($schedule->status) {
        'running'   => 'primary',
        'completed' => 'secondary',
        'cancelled' => 'danger',
        default     => 'warning text-dark',
    };
?>
<span class="badge bg-<?php echo e($statusColor); ?>"><?php echo e($schedule->statusLabel()); ?></span>
<?php /**PATH C:\Working\appdatxe\server\resources\views/partials/schedule-status.blade.php ENDPATH**/ ?>