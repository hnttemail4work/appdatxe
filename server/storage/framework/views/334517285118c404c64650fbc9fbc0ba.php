<?php $__env->startSection('content'); ?>
<?php
$provinces = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột','Phan Thiết','Long Xuyên','Mỹ Tho','Vinh','Thanh Hóa','Hạ Long'];
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3 class="mb-1 card-title-bar">Dashboard</h3>
                    <p class="text-muted mb-0">Quản lý xe, lịch trình và tài xế.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo e(route('operator.drivers')); ?>"
                       class="btn btn-outline-primary btn-sm <?php echo e(request()->routeIs('operator.drivers*') ? 'active' : ''); ?>">
                        Quản lý tài xế
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        
        <div class="card shadow-sm p-4 mb-4">
            <h4>Thêm xe mới</h4>
            <form method="POST" action="<?php echo e(route('operator.vehicles.store')); ?>" class="mt-3">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Biển số xe</label>
                    <input name="license_plate" class="form-control" placeholder="vd: 51A-12345" required value="<?php echo e(old('license_plate')); ?>">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Loại xe</label>
                        <select name="type" class="form-select" required>
                            <option value="limousine" <?php echo e(old('type') === 'limousine' ? 'selected' : ''); ?>>Limousine</option>
                            <option value="sedan" <?php echo e(old('type') === 'sedan' ? 'selected' : ''); ?>>Sedan</option>
                            <option value="suv" <?php echo e(old('type') === 'suv' ? 'selected' : ''); ?>>SUV</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số ghế</label>
                        <input type="number" name="capacity" min="1" max="50" class="form-control"
                            placeholder="9" value="<?php echo e(old('capacity')); ?>" required>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?php echo e(old('status') === 'active' ? 'selected' : ''); ?>>Hoạt động</option>
                        <option value="maintenance" <?php echo e(old('status') === 'maintenance' ? 'selected' : ''); ?>>Đang bảo trì</option>
                        <option value="inactive" <?php echo e(old('status') === 'inactive' ? 'selected' : ''); ?>>Không hoạt động</option>
                    </select>
                </div>
                <button class="btn btn-primary">Lưu xe</button>
            </form>
        </div>

        
        <div class="card shadow-sm p-4">
            <h4>Tạo lịch trình</h4>
            <form method="POST" action="<?php echo e(route('operator.schedules.store')); ?>" class="mt-3">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Tuyến đường</label>
                    <?php if($routes->isNotEmpty()): ?>
                        <select name="route_id" class="form-select" required>
                            <option value="">-- Chọn tuyến --</option>
                            <?php $__currentLoopData = $routes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $route): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($route->id); ?>" <?php echo e(old('route_id') == $route->id ? 'selected' : ''); ?>>
                                    <?php echo e($route->departure); ?> → <?php echo e($route->destination); ?> · <?php echo e(number_format($route->base_price, 0, ',', '.')); ?> đ
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-0">Chưa có tuyến nào trong hệ thống. Liên hệ admin để thêm tuyến.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Xe</label>
                    <?php if($vehicles->isNotEmpty()): ?>
                        <select name="vehicle_id" class="form-select" required>
                            <option value="">-- Chọn xe --</option>
                            <?php $__currentLoopData = $vehicles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vehicle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if($vehicle->status === 'active'): ?>
                                    <option value="<?php echo e($vehicle->id); ?>" <?php echo e(old('vehicle_id') == $vehicle->id ? 'selected' : ''); ?>>
                                        <?php echo e($vehicle->license_plate); ?> · <?php echo e(ucfirst($vehicle->type)); ?> · <?php echo e($vehicle->capacity); ?> ghế
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-0">Bạn chưa có xe. Thêm xe ở trên trước.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tài xế</label>
                    <?php if($drivers->isNotEmpty()): ?>
                        <select name="driver_id" class="form-select mb-2" onchange="fillDriverName(this)">
                            <option value="">-- Chọn từ danh sách --</option>
                            <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($d->user_id); ?>" data-name="<?php echo e($d->user->name); ?>"
                                    <?php echo e(old('driver_id') == $d->user_id ? 'selected' : ''); ?>>
                                    <?php echo e($d->user->name); ?> · Hạng <?php echo e($d->license_class); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    <?php else: ?>
                        <p class="small text-muted mb-1">Chưa có tài xế. <a href="<?php echo e(route('operator.drivers')); ?>">Thêm tài xế →</a></p>
                    <?php endif; ?>
                    <input name="driver_name" id="driver_name_input" class="form-control" placeholder="Hoặc nhập tên tài xế"
                        value="<?php echo e(old('driver_name')); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giờ khởi hành</label>
                    <input type="datetime-local" name="departure_time" class="form-control"
                        value="<?php echo e(old('departure_time')); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Trạng thái lịch</label>
                    <select name="status" class="form-select" required>
                        <option value="scheduled" <?php echo e(old('status') === 'scheduled' ? 'selected' : ''); ?>>Đã lên lịch</option>
                        <option value="draft" <?php echo e(old('status') === 'draft' ? 'selected' : ''); ?>>Nháp</option>
                        <option value="running" <?php echo e(old('status') === 'running' ? 'selected' : ''); ?>>Đang chạy</option>
                        <option value="completed" <?php echo e(old('status') === 'completed' ? 'selected' : ''); ?>>Hoàn thành</option>
                        <option value="cancelled" <?php echo e(old('status') === 'cancelled' ? 'selected' : ''); ?>>Hủy</option>
                    </select>
                </div>
                <button class="btn btn-primary">Tạo lịch trình</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        
        <div class="card shadow-sm p-4 mb-4">
            <h4>Đội xe của tôi</h4>
            <?php if($vehicles->isEmpty()): ?>
                <p class="text-muted mt-2">Chưa có xe nào. Thêm xe ở bên trái.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush mt-3">
                    <?php $__currentLoopData = $vehicles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vehicle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo e($vehicle->license_plate); ?></strong>
                                    <span class="text-muted ms-2"><?php echo e(ucfirst($vehicle->type)); ?> · <?php echo e($vehicle->capacity); ?> ghế</span>
                                </div>
                                <span class="badge bg-<?php echo e($vehicle->status === 'active' ? 'success' : ($vehicle->status === 'maintenance' ? 'warning' : 'secondary')); ?>">
                                    <?php echo e(match($vehicle->status) { 'active' => 'Hoạt động', 'maintenance' => 'Bảo trì', default => 'Không HĐ' }); ?>

                                </span>
                            </div>
                            <?php if($vehicle->schedules->isNotEmpty()): ?>
                                <small class="text-muted"><?php echo e($vehicle->schedules->count()); ?> lịch trình</small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>
        </div>

        
        <div class="card shadow-sm p-4">
            <h4>Hành khách & Booking</h4>
            <?php if($passengers->isEmpty()): ?>
                <p class="text-muted mt-2">Chưa có hành khách nào.</p>
            <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Khách</th>
                                <th>Chuyến</th>
                                <th>Ghế</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $passengers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-bottom">
                                    <td>
                                        <strong><?php echo e($booking->customer->name); ?></strong><br>
                                        <small class="text-muted"><?php echo e($booking->customer->email); ?></small>
                                    </td>
                                    <td>
                                        <?php echo e($booking->schedule->route->departure); ?> → <?php echo e($booking->schedule->route->destination); ?><br>
                                        <small class="text-muted"><?php echo e($booking->schedule->departure_time->format('H:i d/m')); ?></small>
                                    </td>
                                    <td><?php echo e(implode(', ', (array)$booking->seat_numbers)); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo e(match($booking->booking_status) {
                                            'confirmed' => 'primary',
                                            'cancelled','rejected' => 'danger',
                                            default => 'warning text-dark'
                                        }); ?>"><?php echo e(ucfirst($booking->booking_status)); ?></span>
                                        <br>
                                        <small class="badge bg-<?php echo e($booking->payment_status === 'paid' ? 'primary' : 'secondary'); ?> mt-1">
                                            <?php echo e($booking->payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán'); ?>

                                        </small>
                                    </td>
                                    <td>
                                        <?php if($booking->booking_status === 'pending'): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <form method="POST" action="<?php echo e(route('operator.bookings.accept', $booking)); ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <button class="btn btn-sm btn-primary w-100"
                                                        <?php echo e($booking->payment_status !== 'paid' ? 'disabled' : ''); ?>

                                                        title="<?php echo e($booking->payment_status !== 'paid' ? 'Khách chưa thanh toán' : 'Duyệt booking'); ?>">
                                                        Duyệt
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?php echo e(route('operator.bookings.reject', $booking)); ?>"
                                                    onsubmit="return confirm('Từ chối booking này?')">
                                                    <?php echo csrf_field(); ?>
                                                    <button class="btn btn-sm btn-outline-danger w-100">Từ chối</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function fillDriverName(select) {
    const opt = select.options[select.selectedIndex];
    const name = opt.getAttribute('data-name');
    if (name) document.getElementById('driver_name_input').value = name;
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views\operator\dashboard.blade.php ENDPATH**/ ?>