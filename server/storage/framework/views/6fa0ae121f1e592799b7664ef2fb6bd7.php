<?php $__env->startSection('content'); ?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="card-title-bar mb-1">Quản trị hệ thống</h3>
                    <p class="text-muted mb-0">Toàn quyền quản lý người dùng, tài xế, booking và cấu hình.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo e(route('operator.drivers')); ?>" class="btn btn-outline-secondary btn-sm">Tất cả tài xế</a>
                    <a href="<?php echo e(route('admin.bookings')); ?>" class="btn btn-primary btn-sm">Booking & Chi tiêu →</a>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-lg-6">
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="card-title-bar">Thiết lập hoa hồng</h5>
            <form method="POST" action="<?php echo e(route('admin.commission.update')); ?>" class="mt-3">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Tỷ lệ hoa hồng (%)</label>
                    <input type="number" name="commission_percentage"
                        value="<?php echo e($commissionSetting['value'] ?? 10); ?>"
                        min="0" max="100" step="0.1" class="form-control" required>
                    <div class="form-text">Phần trăm <?php echo e(config('app.name')); ?> thu trên mỗi giao dịch.</div>
                </div>
                <button class="btn btn-primary">Cập nhật</button>
            </form>
        </div>

        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar">Tài khoản chờ duyệt</h5>
            <?php if($merchants->isEmpty()): ?>
                <p class="text-muted mt-2">Không có tài khoản nào chờ duyệt.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3 mt-3">
                    <?php $__currentLoopData = $merchants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $merchant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong><?php echo e($merchant->user->name); ?></strong>
                                    <small class="text-muted ms-2"><?php echo e($merchant->user->email); ?></small><br>
                                    <span class="badge bg-<?php echo e(match($merchant->kyc_status) {
                                        'approved'=>'primary','rejected'=>'danger',default=>'warning text-dark'
                                    }); ?>">
                                        <?php echo e(match($merchant->kyc_status) { 'approved'=>'Đã duyệt','rejected'=>'Từ chối',default=>'Chờ duyệt' }); ?>

                                    </span>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if($merchant->kyc_status !== 'approved'): ?>
                                    <form method="POST" action="<?php echo e(route('admin.merchants.approve', $merchant)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button class="btn btn-sm btn-primary">Duyệt</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if($merchant->kyc_status !== 'rejected'): ?>
                                    <form method="POST" action="<?php echo e(route('admin.merchants.reject', $merchant)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="<?php echo e(route('admin.merchants.suspend', $merchant)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button class="btn btn-sm btn-outline-secondary">Tạm ngưng</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="col-lg-6">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar">Đơn hàng gần đây</h5>
            <?php if($orderSummary->isEmpty()): ?>
                <p class="text-muted mt-2">Chưa có đơn hàng.</p>
            <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Mã</th><th>Khách</th><th>Chuyến</th><th>TT</th></tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $orderSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-bottom">
                                    <td><code class="small"><?php echo e($booking->booking_reference); ?></code></td>
                                    <td><?php echo e($booking->customer->name); ?></td>
                                    <td class="small"><?php echo e($booking->schedule->route->departure); ?> → <?php echo e($booking->schedule->route->destination); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo e(match($booking->payment_status) { 'paid'=>'primary','refunded'=>'secondary',default=>'warning text-dark' }); ?>">
                                            <?php echo e(match($booking->payment_status) { 'paid'=>'Đã TT','refunded'=>'Hoàn',default=>'Chưa TT' }); ?>

                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Danh sách Quản lý (<?php echo e($operators->count()); ?>)</h5>
            <?php if($operators->isEmpty()): ?>
                <p class="text-muted">Chưa có quản lý nào.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Tài xế</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $operators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $opDrivers = $drivers->where('operator_id', $op->id); ?>
                            <tr class="border-bottom">
                                <td><strong><?php echo e($op->name); ?></strong></td>
                                <td class="text-muted small"><?php echo e($op->email); ?></td>
                                <td class="text-muted small"><?php echo e($op->phone ?? '—'); ?></td>
                                <td><span class="badge bg-secondary"><?php echo e($opDrivers->count()); ?> tài xế</span></td>
                                <td>
                                    <form method="POST" action="<?php echo e(route('admin.users.status', $op)); ?>"
                                          class="d-flex gap-1 align-items-center">
                                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                        <select name="status" class="form-select form-select-sm" style="width:120px">
                                            <?php $__currentLoopData = ['active','inactive','suspended']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($st); ?>" <?php echo e($op->status === $st ? 'selected' : ''); ?>>
                                                    <?php echo e(match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' }); ?>

                                                </option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#edit-op-<?php echo e($op->id); ?>">Sửa</button>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-op-<?php echo e($op->id); ?>">
                                <td colspan="6" class="bg-light">
                                    <form method="POST" action="<?php echo e(route('admin.users.update', $op)); ?>"
                                          class="row g-2 p-2">
                                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                        <div class="col-md-3">
                                            <input type="text" name="name" value="<?php echo e($op->name); ?>" class="form-control form-control-sm" placeholder="Họ tên" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="email" name="email" value="<?php echo e($op->email); ?>" class="form-control form-control-sm" placeholder="Email" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="tel" name="phone" value="<?php echo e($op->phone); ?>" class="form-control form-control-sm" placeholder="SĐT">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Mật khẩu mới">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-primary w-100">Lưu</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title-bar mb-0">Danh sách tài xế (<?php echo e($drivers->count()); ?>)</h5>
                <a href="<?php echo e(route('operator.drivers')); ?>" class="btn btn-sm btn-outline-primary">Quản lý đầy đủ →</a>
            </div>
            <?php if($drivers->isEmpty()): ?>
                <p class="text-muted mb-0">Chưa có tài xế nào.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>SĐT</th>
                                <th>Hạng</th>
                                <th>Kinh nghiệm</th>
                                <th>Quản lý bởi</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $drivers->take(10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><strong><?php echo e($d->user->name); ?></strong></td>
                                    <td class="text-muted small"><?php echo e($d->user->email); ?></td>
                                    <td class="small"><?php echo e($d->user->phone ?? '—'); ?></td>
                                    <td><span class="badge bg-primary">Hạng <?php echo e($d->license_class); ?></span></td>
                                    <td><?php echo e($d->experience_years); ?> năm</td>
                                    <td class="small"><?php echo e($d->operator?->name ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo e(match($d->status) { 'active'=>'primary','suspended'=>'danger',default=>'secondary' }); ?>">
                                            <?php echo e(match($d->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' }); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo e(route('operator.drivers.edit', $d)); ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <?php if($drivers->count() > 10): ?>
                    <p class="text-muted small mt-2 mb-0">Hiển thị 10/<?php echo e($drivers->count()); ?> tài xế. <a href="<?php echo e(route('operator.drivers')); ?>">Xem tất cả</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="col-12">
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Danh sách khách hàng (<?php echo e($customers->count()); ?>)</h5>
            <?php if($customers->isEmpty()): ?>
                <p class="text-muted">Chưa có khách hàng nào.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light">
                            <tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Ngày ĐK</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-bottom">
                                    <td><strong><?php echo e($c->name); ?></strong></td>
                                    <td class="text-muted small"><?php echo e($c->email); ?></td>
                                    <td class="text-muted small"><?php echo e($c->phone ?? '—'); ?></td>
                                    <td class="text-muted small"><?php echo e($c->created_at->format('d/m/Y')); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo e(route('admin.users.status', $c)); ?>"
                                              class="d-flex gap-1 align-items-center">
                                            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                            <select name="status" class="form-select form-select-sm" style="width:120px">
                                                <?php $__currentLoopData = ['active','inactive','suspended']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($st); ?>" <?php echo e($c->status === $st ? 'selected' : ''); ?>>
                                                        <?php echo e(match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' }); ?>

                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Lưu</button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-cust-<?php echo e($c->id); ?>">Sửa</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="edit-cust-<?php echo e($c->id); ?>">
                                    <td colspan="6" class="bg-light">
                                        <form method="POST" action="<?php echo e(route('admin.users.update', $c)); ?>"
                                              class="row g-2 p-2">
                                            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                            <div class="col-md-3">
                                                <input type="text" name="name" value="<?php echo e($c->name); ?>" class="form-control form-control-sm" placeholder="Họ tên" required>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="email" name="email" value="<?php echo e($c->email); ?>" class="form-control form-control-sm" placeholder="Email" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="tel" name="phone" value="<?php echo e($c->phone); ?>" class="form-control form-control-sm" placeholder="SĐT">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="password" name="password" class="form-control form-control-sm" placeholder="Mật khẩu mới">
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-sm btn-primary w-100">Lưu</button>
                                            </div>
                                        </form>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>