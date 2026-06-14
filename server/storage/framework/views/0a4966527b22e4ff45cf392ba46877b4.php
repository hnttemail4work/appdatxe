<?php $__env->startSection('content'); ?>
<div class="row g-4">

    
    <div class="col-lg-4">

        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex gap-3 align-items-start mb-3">
                <div class="flex-shrink-0">
                    <?php if($profile?->photo_portrait): ?>
                        <img src="<?php echo e($profile->photoUrl('photo_portrait')); ?>" alt="Chân dung"
                             class="rounded-circle object-fit-cover border" style="width:60px;height:60px;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center border"
                             style="width:60px;height:60px;font-size:1.4rem;font-weight:700;">
                            <?php echo e(mb_substr($user->name, 0, 1)); ?>

                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo e($user->name); ?></h5>
                    <span class="badge bg-primary small">Tài xế</span>
                    <?php if($profile): ?>
                        <span class="badge bg-<?php echo e(match($profile->status) { 'active'=>'success','suspended'=>'danger',default=>'secondary' }); ?> small ms-1">
                            <?php echo e(match($profile->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' }); ?>

                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($profile): ?>
            <div class="d-flex flex-column gap-2 small mb-3">
                <?php if($profile->driver_code): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Mã tài xế</span>
                    <code class="fw-bold"><?php echo e($profile->driver_code); ?></code>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Điện thoại</span>
                    <span><?php echo e($user->phone ?? '—'); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Hạng bằng</span>
                    <span class="badge bg-primary">Hạng <?php echo e($profile->license_class); ?></span>
                </div>
                <?php if($profile->operator): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Quản lý</span>
                    <span><?php echo e($profile->operator->name); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <a href="<?php echo e(route('driver.profile')); ?>" class="btn btn-outline-primary btn-sm w-100">
                Cập nhật hồ sơ & ảnh →
            </a>
            <?php else: ?>
            <div class="alert alert-warning py-2 small mb-0">
                Chưa có hồ sơ tài xế. Liên hệ quản lý.
            </div>
            <?php endif; ?>
        </div>

        <?php if($profile): ?>
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Trạng thái hoạt động</h5>
            <?php
                $avail = $profile->availability_status ?? 'off_duty';
                $availConfig = [
                    'available' => ['label' => 'Sẵn sàng nhận chuyến', 'color' => 'success',  'icon' => '🟢'],
                    'on_trip'   => ['label' => 'Đang chạy chuyến',     'color' => 'primary',   'icon' => '🔵'],
                    'off_duty'  => ['label' => 'Nghỉ / Không nhận',    'color' => 'secondary', 'icon' => '⚫'],
                ];
            ?>
            <div class="mb-3 text-center">
                <span class="fs-4"><?php echo e($availConfig[$avail]['icon']); ?></span>
                <div class="mt-1">
                    <span class="badge bg-<?php echo e($availConfig[$avail]['color']); ?> fs-6 px-3 py-2">
                        <?php echo e($availConfig[$avail]['label']); ?>

                    </span>
                </div>
            </div>
            <form method="POST" action="<?php echo e(route('driver.availability.update')); ?>">
                <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <div class="d-flex flex-column gap-2">
                    <?php $__currentLoopData = $availConfig; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $cfg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="submit" name="availability_status" value="<?php echo e($val); ?>"
                            class="btn btn-<?php echo e($avail === $val ? $cfg['color'] : 'outline-'.$cfg['color']); ?> text-start">
                            <?php echo e($cfg['icon']); ?> <?php echo e($cfg['label']); ?>

                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="col-lg-8">
        <div class="card shadow-sm p-4 mb-4" id="pending-requests-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title-bar mb-0">Yêu cầu nhận chuyến</h4>
                <small class="text-muted" id="driver-sync-indicator">Đang cập nhật...</small>
            </div>
            <?php if($pendingRequests->isEmpty()): ?>
                <p class="text-muted mb-0" id="no-pending-msg">Không có yêu cầu mới.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3" id="pending-requests-list">
                    <?php $__currentLoopData = $pendingRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border border-warning rounded-3 p-3 bg-warning-subtle">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <strong><?php echo e($req->schedule->route->departure); ?> → <?php echo e($req->schedule->route->destination); ?></strong><br>
                                <span class="text-muted small"><?php echo e($req->schedule->departure_time->format('H:i · d/m/Y')); ?></span><br>
                                <span class="small">Khách: <strong><?php echo e($req->customer->name); ?></strong> · <?php echo e($req->customer->phone ?? '—'); ?></span>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <form method="POST" action="<?php echo e(route('driver.tripRequests.accept', $req)); ?>"><?php echo csrf_field(); ?>
                                    <button class="btn btn-success btn-sm">Nhận chuyến</button>
                                </form>
                                <form method="POST" action="<?php echo e(route('driver.tripRequests.reject', $req)); ?>"><?php echo csrf_field(); ?>
                                    <button class="btn btn-outline-danger btn-sm">Từ chối</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Lịch chạy của tôi</h4>
            <?php if($schedules->isEmpty()): ?>
                <p class="text-muted">Chưa có lịch chạy nào được phân công.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php $__currentLoopData = $schedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $isToday = $s->departure_time->isToday(); ?>
                    <div class="border rounded-3 p-3 <?php echo e($isToday ? 'border-primary bg-light' : ''); ?>">
                        <div class="row align-items-center g-2">
                            <div class="col-md-4">
                                <?php if($isToday): ?>
                                    <span class="badge bg-primary mb-1">Hôm nay</span><br>
                                <?php endif; ?>
                                <strong><?php echo e($s->route->departure); ?> → <?php echo e($s->route->destination); ?></strong><br>
                                <span class="text-muted small"><?php echo e($s->departure_time->format('H:i · d/m/Y')); ?></span>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Xe</span>
                                <?php echo e(ucfirst($s->vehicle->type)); ?><br>
                                <small class="text-muted"><?php echo e($s->vehicle->license_plate); ?> · <?php echo e($s->vehicle->capacity); ?> ghế</small>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Đã đặt</span>
                                <strong><?php echo e($s->bookedSeatsCount()); ?></strong>
                                <span class="text-muted">/ <?php echo e($s->capacity()); ?> ghế</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-<?php echo e(match($s->status) {
                                    'running'   => 'primary',
                                    'completed' => 'secondary',
                                    'cancelled' => 'danger',
                                    default     => 'warning text-dark'
                                }); ?>">
                                    <?php echo e(match($s->status) {
                                        'scheduled' => 'Đã lên lịch',
                                        'running'   => 'Đang chạy',
                                        'completed' => 'Hoàn thành',
                                        'cancelled' => 'Đã hủy',
                                        default     => ucfirst($s->status)
                                    }); ?>

                                </span>
                            </div>
                        </div>

                        <?php if($s->bookings->isNotEmpty()): ?>
                        <hr class="my-2">
                        <div class="small">
                            <span class="text-muted fw-semibold">Hành khách (<?php echo e($s->bookings->count()); ?>):</span>
                            <div class="d-flex flex-column gap-2 mt-2">
                                <?php $__currentLoopData = $s->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 rounded-2 px-3 py-2 border <?php echo e($booking->isConfirmedForDriver() ? 'bg-success-subtle border-success' : 'bg-white'); ?>">
                                    <div>
                                        <strong><?php echo e($booking->customer->name); ?></strong>
                                        <span class="text-muted"> · <?php echo e($booking->customer->phone ?? '—'); ?></span><br>
                                        <span class="text-muted">Ghế <?php echo e(implode(', ', (array) $booking->seat_numbers)); ?></span>
                                        <?php if($booking->pickup_address): ?>
                                            · <span class="text-muted">Đón: <?php echo e($booking->pickup_address); ?></span>
                                        <?php endif; ?>
                                        <?php if($booking->dropoff_address): ?>
                                            · <span class="text-muted">Trả: <?php echo e($booking->dropoff_address); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?php echo $__env->make('partials.booking-status', ['booking' => $booking], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                        <?php if($booking->trip_status === 'confirmed'): ?>
                                            <form method="POST" action="<?php echo e(route('driver.bookings.complete', $booking)); ?>" class="mt-1"
                                                onsubmit="return confirm('Báo hoàn thành chuyến cho khách <?php echo e($booking->customer->name); ?>?')">
                                                <?php echo csrf_field(); ?>
                                                <button class="btn btn-sm btn-outline-success">Báo hoàn thành chuyến</button>
                                            </form>
                                        <?php elseif($booking->trip_status === 'awaiting_completion'): ?>
                                            <span class="badge bg-info text-dark mt-1">Chờ khách xác nhận</span>
                                        <?php elseif($booking->trip_status === 'completed'): ?>
                                            <span class="badge bg-success mt-1">Đã hoàn tất</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <hr class="my-2">
                        <p class="text-muted small mb-0">Chưa có hành khách đặt vé cho chuyến này.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    var syncUrl = <?php echo json_encode(route('driver.liveSync'), 15, 512) ?>;
    function poll() {
        fetch(syncUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var el = document.getElementById('driver-sync-indicator');
                if (el) el.textContent = 'Cập nhật: ' + new Date().toLocaleTimeString('vi-VN');
                var list = document.getElementById('pending-requests-list');
                var empty = document.getElementById('no-pending-msg');
                if (!data.pending_requests.length) {
                    if (list) list.innerHTML = '';
                    if (empty) empty.style.display = 'block';
                    return;
                }
                if (empty) empty.style.display = 'none';
                if (!list) return;
                list.innerHTML = data.pending_requests.map(function (req) {
                    return '<div class="border border-warning rounded-3 p-3 bg-warning-subtle">' +
                        '<strong>' + req.route + '</strong><br>' +
                        '<span class="text-muted small">' + req.departure_time + '</span><br>' +
                        '<span class="small">Khách: <strong>' + req.customer_name + '</strong></span>' +
                        '<div class="mt-2"><span class="badge bg-warning text-dark">Chờ bạn phản hồi</span></div></div>';
                }).join('');
            }).catch(function () {});
    }
    poll();
    setInterval(poll, 10000);
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/driver/dashboard.blade.php ENDPATH**/ ?>