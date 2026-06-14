

<?php $__env->startSection('content'); ?>
<?php
    $vehicleUrls = $driver->vehiclePhotoUrls();
?>
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Sửa tài xế: <?php echo e($driver->user->name); ?></h3>
            <p class="text-muted mb-0">Cập nhật thông tin, trạng thái và ảnh hồ sơ.</p>
        </div>
        <a href="<?php echo e(route('operator.drivers')); ?>" class="btn btn-outline-secondary btn-sm">← Danh sách tài xế</a>
    </div>

    
    <div class="col-lg-8">
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="mb-3">Thông tin tài xế</h5>
            <form method="POST" action="<?php echo e(route('operator.drivers.update', $driver)); ?>">
                <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <?php echo $__env->make('partials.driver-form-fields', [
                    'mode'      => 'edit',
                    'driver'    => $driver,
                    'operators' => $operators,
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary">Lưu thông tin</button>
                    <a href="<?php echo e(route('operator.drivers')); ?>" class="btn btn-outline-secondary">Huỷ</a>
                </div>
            </form>
        </div>

        
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="mb-3">Ảnh hồ sơ hiện tại</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">Chân dung</small>
                    <?php if($driver->photo_portrait): ?>
                        <a href="<?php echo e($driver->photoUrl('photo_portrait')); ?>" target="_blank">
                            <img src="<?php echo e($driver->photoUrl('photo_portrait')); ?>" alt="Chân dung"
                                 class="rounded border object-fit-cover" style="width:100%;max-width:120px;height:140px;">
                        </a>
                    <?php else: ?>
                        <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                             style="width:120px;height:140px;font-size:.75rem;">Chưa có</div>
                    <?php endif; ?>
                </div>
                <?php $__currentLoopData = ['photo_id_card' => 'CCCD mặt trước', 'photo_id_card_back' => 'CCCD mặt sau']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1"><?php echo e($lbl); ?></small>
                    <?php if($driver->{$col}): ?>
                        <a href="<?php echo e($driver->photoUrl($col)); ?>" target="_blank">
                            <img src="<?php echo e($driver->photoUrl($col)); ?>" alt="<?php echo e($lbl); ?>"
                                 class="rounded border object-fit-cover" style="width:100%;max-width:160px;height:100px;">
                        </a>
                    <?php else: ?>
                        <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                             style="width:160px;height:100px;font-size:.75rem;">Chưa có</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div class="col-12">
                    <small class="text-muted d-block mb-1">Ảnh xe (<?php echo e(count($vehicleUrls)); ?>)</small>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php $__empty_1 = true; $__currentLoopData = $vehicleUrls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="position-relative">
                                <a href="<?php echo e($url); ?>" target="_blank">
                                    <img src="<?php echo e($url); ?>" alt="Xe <?php echo e($idx+1); ?>"
                                         class="rounded border object-fit-cover" style="width:90px;height:64px;">
                                </a>
                                <form method="POST" action="<?php echo e(route('operator.drivers.photos', $driver)); ?>"
                                      class="d-inline" style="position:absolute;top:-6px;right:-6px;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_vehicle_idx" value="<?php echo e($idx); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm p-0 lh-1"
                                            style="width:18px;height:18px;font-size:.65rem;"
                                            onclick="return confirm('Xóa ảnh này?')" title="Xóa">×</button>
                                </form>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <span class="text-muted small">Chưa có ảnh xe.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card shadow-sm p-4">
            <h5 class="mb-3">Upload / cập nhật ảnh</h5>
            <?php $__errorArgs = ['photos'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <div class="alert alert-danger py-2"><?php echo e($message); ?></div>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php echo $__env->make('partials.driver-photo-upload-form', [
                'action'      => route('operator.drivers.photos', $driver),
                'title'       => null,
                'submitLabel' => 'Lưu ảnh',
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>

    
    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h6 class="text-muted mb-3">Tóm tắt</h6>
            <dl class="small mb-0">
                <dt class="text-muted">Email</dt>
                <dd><?php echo e($driver->user->email); ?></dd>
                <dt class="text-muted">Quản lý bởi</dt>
                <dd><?php echo e($driver->operator?->name ?? '—'); ?></dd>
                <dt class="text-muted">Ngày tạo hồ sơ</dt>
                <dd><?php echo e($driver->created_at->format('d/m/Y H:i')); ?></dd>
            </dl>
            <?php if($driver->status !== 'inactive'): ?>
            <hr>
            <form method="POST" action="<?php echo e(route('operator.drivers.destroy', $driver)); ?>"
                  onsubmit="return confirm('Vô hiệu hoá tài xế này? Tài xế sẽ không đăng nhập được.')">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button class="btn btn-sm btn-outline-danger w-100">Vô hiệu hoá tài xế</button>
            </form>
            <?php else: ?>
            <div class="alert alert-secondary small mt-3 mb-0 py-2">Tài xế đang ở trạng thái không hoạt động.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/operator/drivers/edit.blade.php ENDPATH**/ ?>