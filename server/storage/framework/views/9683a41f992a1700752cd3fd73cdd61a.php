<?php $__env->startSection('content'); ?>
<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title-bar mb-1">Quản lý Booking &amp; Chi tiêu</h3>
                <p class="text-muted mb-0">Toàn bộ đặt chuyến trong hệ thống.</p>
            </div>
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
        </div>
    </div>

    
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-primary"><?php echo e(number_format($stats['total'])); ?></div>
            <div class="text-muted small">Tổng booking</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-success"><?php echo e(number_format($stats['paid'])); ?></div>
            <div class="text-muted small">Đã thanh toán</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-warning"><?php echo e(number_format($stats['unpaid'])); ?></div>
            <div class="text-muted small">Chưa thanh toán</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm p-3 text-center">
            <div class="fs-3 fw-bold text-primary"><?php echo e(number_format($stats['revenue'], 0, ',', '.')); ?> đ</div>
            <div class="text-muted small">Doanh thu (đã TT)</div>
        </div>
    </div>

    
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Chi tiết booking</h5>
            <div class="table-responsive">
                <table class="table table-borderless align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Mã</th>
                            <th>Khách hàng</th>
                            <th>Chuyến</th>
                            <th>Tài xế</th>
                            <th>Ghế</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán / Booking</th>
                            <th>Ngày đặt</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-bottom">
                            <td>
                                <code class="small"><?php echo e($b->ticket_code); ?></code><br>
                                <span class="text-muted" style="font-size:.7rem;"><?php echo e($b->booking_reference); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($b->customer->name); ?></strong><br>
                                <small class="text-muted"><?php echo e($b->customer->email); ?></small>
                            </td>
                            <td>
                                <strong><?php echo e($b->schedule->route->departure); ?> → <?php echo e($b->schedule->route->destination); ?></strong><br>
                                <small class="text-muted"><?php echo e($b->schedule->departure_time->format('H:i d/m/Y')); ?></small><br>
                                <small class="text-muted"><?php echo e(ucfirst($b->schedule->vehicle->type)); ?> · <?php echo e($b->schedule->vehicle->license_plate); ?></small>
                            </td>
                            <td class="small">
                                <?php if($b->schedule->driver): ?>
                                    <?php echo e($b->schedule->driver->name); ?>

                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?php echo e(implode(', ', (array) $b->seat_numbers)); ?></td>
                            <td class="fw-semibold"><?php echo e(number_format($b->total_price, 0, ',', '.')); ?> đ</td>
                            <td><?php echo $__env->make('partials.booking-status', ['booking' => $b], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
                            <td class="text-muted small"><?php echo e($b->created_at->format('d/m/Y H:i')); ?></td>
                            <td><?php echo $__env->make('partials.booking-actions', ['booking' => $b, 'routePrefix' => 'admin'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            
            <div class="mt-3">
                <?php echo e($bookings->links()); ?>

            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/admin/bookings.blade.php ENDPATH**/ ?>