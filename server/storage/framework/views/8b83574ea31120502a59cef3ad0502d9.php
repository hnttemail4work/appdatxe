<?php $__env->startSection('content'); ?>
<?php
$provinces = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột','Phan Thiết','Long Xuyên','Mỹ Tho','Vinh','Thanh Hóa','Hạ Long'];
?>
<div class="row g-4">

    
    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Tìm chuyến xe</h4>
            <form class="row g-3" method="GET" action="<?php echo e(route('customer.dashboard')); ?>">
                <div class="col-md-4">
                    <label class="form-label">Điểm đi</label>
                    <select name="departure" class="form-select" required>
                        <option value="">-- Chọn điểm đi --</option>
                        <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($p); ?>" <?php echo e(request('departure') === $p ? 'selected' : ''); ?>><?php echo e($p); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Điểm đến</label>
                    <select name="destination" class="form-select" required>
                        <option value="">-- Chọn điểm đến --</option>
                        <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($p); ?>" <?php echo e(request('destination') === $p ? 'selected' : ''); ?>><?php echo e($p); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ngày đi</label>
                    <input type="date" name="date"
                        value="<?php echo e(request('date') ?? now()->addDay()->format('Y-m-d')); ?>"
                        class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại xe</label>
                    <select name="vehicle_type" class="form-select">
                        <option value="">Tất cả loại xe</option>
                        <option value="limousine" <?php echo e(request('vehicle_type') === 'limousine' ? 'selected' : ''); ?>>Limousine</option>
                        <option value="sedan"     <?php echo e(request('vehicle_type') === 'sedan'     ? 'selected' : ''); ?>>Sedan</option>
                        <option value="suv"       <?php echo e(request('vehicle_type') === 'suv'       ? 'selected' : ''); ?>>SUV</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Tìm chuyến</button>
                </div>
            </form>
        </div>

        
        <?php if($availableDrivers->isNotEmpty()): ?>
        <div class="card shadow-sm p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title-bar mb-0">Tài xế sẵn sàng</h4>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnRandomDriver">
                    🎲 Ngẫu nhiên
                </button>
            </div>
            <div class="row g-3" id="driverCards">
                <?php $__currentLoopData = $availableDrivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-md-6 col-lg-4 driver-card">
                    <div class="border rounded-3 p-3 h-100 d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;font-weight:700;font-size:1.1rem;">
                            <?php echo e(mb_substr($d->user->name, 0, 1)); ?>

                        </div>
                        <div>
                            <strong class="d-block"><?php echo e($d->user->name); ?></strong>
                            <span class="text-muted small">
                                Hạng <?php echo e($d->license_class); ?> · <?php echo e($d->experience_years); ?> năm KN
                            </span><br>
                            <?php if($d->operator): ?>
                            <small class="text-muted"><?php echo e($d->operator->name); ?></small>
                            <?php endif; ?>
                            <div class="mt-1">
                                <span class="badge bg-success">🟢 Sẵn sàng</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div id="randomResult" class="mt-3" style="display:none;">
                <div class="alert alert-primary mb-0 d-flex gap-3 align-items-center">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:44px;height:44px;font-weight:700;font-size:1.1rem;" id="randomAvatar"></div>
                    <div>
                        <div class="fw-bold" id="randomName"></div>
                        <div class="small text-muted" id="randomMeta"></div>
                        <span class="badge bg-success mt-1">🎲 Được chọn ngẫu nhiên</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        
        <div class="card shadow-sm p-4 mt-4">
            <h4 class="card-title-bar mb-3">Kết quả</h4>            <?php if($searchPerformed && $schedules->isEmpty()): ?>
                <div class="alert alert-info">Không tìm thấy chuyến phù hợp. Thử thay đổi ngày hoặc tuyến đường.</div>
            <?php elseif(!$searchPerformed): ?>
                <p class="text-muted">Chọn điểm đi, điểm đến và ngày để tìm chuyến.</p>
            <?php endif; ?>

            <?php $__currentLoopData = $schedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="border rounded-3 p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <strong class="d-block"><?php echo e($s->route->departure); ?> → <?php echo e($s->route->destination); ?></strong>
                        <span class="text-muted small"><?php echo e($s->departure_time->format('H:i · d/m/Y')); ?></span>
                    </div>
                    <div class="col-md-2">
                        <span class="small text-muted d-block">Xe</span>
                        <?php echo e(ucfirst($s->vehicle->type)); ?><br>
                        <small class="text-muted"><?php echo e($s->vehicle->license_plate); ?></small>
                    </div>
                    <div class="col-md-3">
                        <span class="small text-muted d-block">Tài xế</span>
                        <?php if($s->driver): ?>
                            <strong><?php echo e($s->driver->name); ?></strong>
                            <?php if($s->driver->driverProfile): ?>
                                <br><small class="text-muted">
                                    Hạng <?php echo e($s->driver->driverProfile->license_class); ?> ·
                                    <?php echo e($s->driver->driverProfile->experience_years); ?> năm KN
                                </small>
                            <?php endif; ?>
                        <?php elseif($s->driver_name): ?>
                            <span><?php echo e($s->driver_name); ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 text-center">
                        <span class="badge <?php echo e($s->available_seats > 0 ? 'bg-primary' : 'bg-danger'); ?> mb-1">
                            <?php echo e($s->available_seats); ?> ghế trống
                        </span><br>
                        <strong class="text-primary"><?php echo e(number_format($s->route->base_price, 0, ',', '.')); ?> đ</strong>
                    </div>
                    <div class="col-md-2 text-end">
                        <?php if($s->available_seats > 0): ?>
                            <button class="btn btn-primary btn-sm"
                                data-bs-toggle="collapse" data-bs-target="#book-<?php echo e($s->id); ?>">
                                Đặt vé
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">Hết chỗ</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($s->available_seats > 0): ?>
                <div class="collapse mt-3" id="book-<?php echo e($s->id); ?>">
                    <hr class="my-2">
                    <form method="POST" action="<?php echo e(route('bookings.store')); ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="schedule_id" value="<?php echo e($s->id); ?>">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Ghế <small class="text-muted">(vd: 1,2,3)</small></label>
                                <input type="text" name="seat_numbers" class="form-control"
                                    placeholder="1,2" required>
                                <div class="form-text">Xe <?php echo e($s->vehicle->capacity); ?> ghế (1–<?php echo e($s->vehicle->capacity); ?>)</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm đón</label>
                                <select name="pickup_address" class="form-select">
                                    <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="Bến xe <?php echo e($p); ?>"
                                            <?php echo e($p === $s->route->departure ? 'selected' : ''); ?>>
                                            Bến xe <?php echo e($p); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Điểm trả</label>
                                <select name="dropoff_address" class="form-select">
                                    <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="Bến xe <?php echo e($p); ?>"
                                            <?php echo e($p === $s->route->destination ? 'selected' : ''); ?>>
                                            Bến xe <?php echo e($p); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ghi chú</label>
                                <input type="text" name="notes" class="form-control"
                                    placeholder="Yêu cầu đặc biệt...">
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2 align-items-center">
                                <small class="text-muted">Ghế được giữ 15 phút</small>
                                <button class="btn btn-primary px-4">Xác nhận đặt vé</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    
    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Vé của tôi</h4>
            <?php if($bookings->isEmpty()): ?>
                <p class="text-muted">Bạn chưa có vé nào.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php $__currentLoopData = $bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <strong><?php echo e($b->schedule->route->departure); ?> → <?php echo e($b->schedule->route->destination); ?></strong><br>
                                <small class="text-muted"><?php echo e($b->schedule->departure_time->format('H:i · d/m/Y')); ?></small>
                            </div>
                            <span class="badge bg-<?php echo e(match($b->booking_status) {
                                'confirmed' => 'primary',
                                'cancelled','rejected' => 'danger',
                                default => 'warning text-dark'
                            }); ?>">
                                <?php echo e(match($b->booking_status) {
                                    'confirmed' => 'Xác nhận',
                                    'cancelled' => 'Đã hủy',
                                    'rejected'  => 'Từ chối',
                                    default     => 'Chờ duyệt'
                                }); ?>

                            </span>
                        </div>
                        <div class="small text-muted mb-2">
                            Ghế: <strong><?php echo e(implode(', ', (array)$b->seat_numbers)); ?></strong> ·
                            <?php echo e(ucfirst($b->schedule->vehicle->type)); ?>

                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 small">
                            <span>Tổng: <strong><?php echo e(number_format($b->total_price, 0, ',', '.')); ?> đ</strong></span>
                            <span class="badge bg-<?php echo e(match($b->payment_status) {
                                'paid' => 'primary', 'refunded' => 'secondary', default => 'warning text-dark'
                            }); ?>">
                                <?php echo e(match($b->payment_status) {
                                    'paid' => 'Đã thanh toán', 'refunded' => 'Hoàn tiền', default => 'Chưa thanh toán'
                                }); ?>

                            </span>
                        </div>
                        <div class="small text-muted mb-2">Mã vé: <code><?php echo e($b->ticket_code); ?></code></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if($b->payment_status === 'unpaid' && !in_array($b->booking_status, ['cancelled','rejected'])): ?>
                                <form method="POST" action="<?php echo e(route('bookings.markPaid', $b)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button class="btn btn-sm btn-primary">Xác nhận đã thanh toán</button>
                                </form>
                            <?php endif; ?>
                            <?php if(!in_array($b->booking_status, ['cancelled','rejected'])): ?>
                                <form method="POST" action="<?php echo e(route('bookings.cancel', $b)); ?>"
                                    onsubmit="return confirm('Hủy vé <?php echo e($b->ticket_code); ?>?')">
                                    <?php echo csrf_field(); ?>
                                    <button class="btn btn-sm btn-outline-danger">Hủy vé</button>
                                </form>
                            <?php endif; ?>
                        </div>
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
    const btn = document.getElementById('btnRandomDriver');
    if (!btn) return;

    <?php
        $driverJson = $availableDrivers->map(function($d) {
            return [
                'name'  => $d->user->name,
                'class' => $d->license_class,
                'exp'   => $d->experience_years,
                'team'  => $d->operator?->name ?? '',
            ];
        })->values()->all();
    ?>
    const drivers = <?php echo json_encode($driverJson, 15, 512) ?>;

    btn.addEventListener('click', function () {
        if (!drivers.length) return;
        const d = drivers[Math.floor(Math.random() * drivers.length)];
        document.getElementById('randomAvatar').textContent = d.name.charAt(0);
        document.getElementById('randomName').textContent   = d.name;
        document.getElementById('randomMeta').textContent   =
            'Hạng ' + d.class + ' · ' + d.exp + ' năm KN' + (d.team ? ' · ' + d.team : '');
        document.getElementById('randomResult').style.display = 'block';
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views\customer\dashboard.blade.php ENDPATH**/ ?>