

<?php $__env->startSection('content'); ?>
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Quản lý tài xế</h3>
            <p class="text-muted mb-0">Danh sách tài xế — bấm Sửa để chỉnh thông tin hoặc upload ảnh.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('operator.drivers.create')); ?>" class="btn btn-primary btn-sm">+ Thêm tài xế</a>
            <a href="<?php echo e(route(auth()->user()->role === 'admin' ? 'admin.dashboard' : 'operator.dashboard')); ?>"
               class="btn btn-outline-secondary btn-sm">← Về Dashboard</a>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php if($drivers->isEmpty()): ?>
                    <div class="p-4 text-center text-muted">
                        <p class="mb-2">Chưa có tài xế nào.</p>
                        <a href="<?php echo e(route('operator.drivers.create')); ?>" class="btn btn-sm btn-primary">Thêm tài xế đầu tiên</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:48px"></th>
                                    <th>Họ tên</th>
                                    <th>Mã TX</th>
                                    <th>Liên hệ</th>
                                    <th>Bằng lái</th>
                                    <th>Kinh nghiệm</th>
                                    <?php if(auth()->user()->role === 'admin'): ?>
                                        <th>Quản lý</th>
                                    <?php endif; ?>
                                    <th>Trạng thái</th>
                                    <th>Hồ sơ ảnh</th>
                                    <th style="width:100px">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $vehicleCount = count($d->photo_vehicles ?? []);
                                    $docCount = collect(['photo_portrait','photo_id_card','photo_id_card_back'])
                                        ->filter(fn($c) => $d->{$c})->count();
                                ?>
                                <tr>
                                    <td>
                                        <?php if($d->photo_portrait): ?>
                                            <img src="<?php echo e($d->photoUrl('photo_portrait')); ?>" alt=""
                                                 class="rounded-circle object-fit-cover border"
                                                 style="width:36px;height:36px;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                                 style="width:36px;height:36px;font-size:.85rem;font-weight:700;">
                                                <?php echo e(mb_substr($d->user->name, 0, 1)); ?>

                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e($d->user->name); ?></strong>
                                        <?php if($d->user->id_number): ?>
                                            <br><small class="text-muted">CCCD: <?php echo e($d->user->id_number); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo e($d->driver_code ?? '—'); ?></code></td>
                                    <td class="small">
                                        <?php echo e($d->user->phone ?? '—'); ?><br>
                                        <span class="text-muted"><?php echo e($d->user->email); ?></span>
                                    </td>
                                    <td class="small">
                                        Hạng <strong><?php echo e($d->license_class); ?></strong><br>
                                        HH: <?php echo e($d->license_expiry->format('d/m/Y')); ?>

                                        <?php if($d->license_expiry->isPast()): ?>
                                            <span class="badge bg-danger">Hết hạn</span>
                                        <?php elseif($d->license_expiry->diffInDays(now()) < 60): ?>
                                            <span class="badge bg-warning text-dark">Sắp HH</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($d->experience_years); ?> năm</td>
                                    <?php if(auth()->user()->role === 'admin'): ?>
                                        <td class="small"><?php echo e($d->operator?->name ?? '—'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-<?php echo e(match($d->status) { 'active'=>'primary','suspended'=>'danger',default=>'secondary' }); ?>">
                                            <?php echo e(match($d->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' }); ?>

                                        </span>
                                        <br>
                                        <span class="badge bg-<?php echo e(match($d->availability_status ?? 'off_duty') {
                                            'available'=>'success','on_trip'=>'info',default=>'secondary'
                                        }); ?> mt-1">
                                            <?php echo e(match($d->availability_status ?? 'off_duty') {
                                                'available'=>'Sẵn sàng','on_trip'=>'Đang chạy',default=>'Nghỉ'
                                            }); ?>

                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo e($docCount); ?>/3 giấy tờ<br>
                                        <?php echo e($vehicleCount); ?> ảnh xe
                                    </td>
                                    <td>
                                        <a href="<?php echo e(route('operator.drivers.edit', $d)); ?>"
                                           class="btn btn-sm btn-outline-primary">Sửa</a>
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
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/operator/drivers/index.blade.php ENDPATH**/ ?>