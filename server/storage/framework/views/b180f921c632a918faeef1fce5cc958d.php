<?php if(in_array($booking->booking_status, ['pending']) || ($booking->payment_status === 'unpaid' && !in_array($booking->booking_status, ['cancelled', 'rejected']))): ?>
    <div class="d-flex flex-column gap-1">
        <?php if($booking->payment_status === 'unpaid'): ?>
            <form method="POST" action="<?php echo e(route($routePrefix . '.bookings.confirmPayment', $booking)); ?>">
                <?php echo csrf_field(); ?>
                <button class="btn btn-sm btn-success w-100"
                    title="<?php echo e($booking->hasPendingPaymentClaim() ? 'Khách đã báo chuyển khoản — quản lý xác nhận' : 'Xác nhận đã thu tiền'); ?>">
                    <?php echo e($booking->hasPendingPaymentClaim() ? '✓ Xác nhận đã TT' : 'Xác nhận thanh toán'); ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if($booking->payment_status === 'paid' && $booking->booking_status === 'pending'): ?>
            <form method="POST" action="<?php echo e(route($routePrefix . '.bookings.accept', $booking)); ?>">
                <?php echo csrf_field(); ?>
                <button class="btn btn-sm btn-primary w-100">Duyệt chuyến → Tài xế</button>
            </form>
        <?php endif; ?>

        <?php if($booking->booking_status === 'pending'): ?>
            <form method="POST" action="<?php echo e(route($routePrefix . '.bookings.reject', $booking)); ?>"
                onsubmit="return confirm('Từ chối booking này?')">
                <?php echo csrf_field(); ?>
                <button class="btn btn-sm btn-outline-danger w-100">Từ chối</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif($booking->booking_status === 'confirmed'): ?>
    <?php if($booking->trip_status === 'completed'): ?>
        <span class="badge bg-success">✓ Hoàn tất</span>
    <?php elseif($booking->trip_status === 'awaiting_completion'): ?>
        <span class="badge bg-info text-dark">Chờ KH xác nhận</span>
    <?php else: ?>
        <span class="badge bg-primary">Đã duyệt — tài xế nhận</span>
    <?php endif; ?>
<?php else: ?>
    <span class="text-muted small">—</span>
<?php endif; ?>
<?php /**PATH C:\Working\appdatxe\server\resources\views/partials/booking-actions.blade.php ENDPATH**/ ?>