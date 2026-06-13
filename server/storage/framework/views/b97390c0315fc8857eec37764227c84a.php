<?php $__env->startSection('content'); ?>
<div class="row g-4">

    
    <div class="col-lg-4">

        
        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex gap-3 align-items-start mb-3">
                
                <div class="flex-shrink-0">
                    <?php if($profile?->photo_portrait): ?>
                        <img src="<?php echo e($profile->photoUrl('photo_portrait')); ?>" alt="Chân dung"
                             class="rounded-circle object-fit-cover border"
                             style="width:60px;height:60px;">
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

            <h6 class="card-title-bar mb-3">Thông tin cá nhân</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Email</span>
                    <span class="small"><?php echo e($user->email); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Điện thoại</span>
                    <span class="small"><?php echo e($user->phone ?? '—'); ?></span>
                </div>
                <?php if($user->address): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Địa chỉ</span>
                    <span class="small text-end"><?php echo e($user->address); ?></span>
                </div>
                <?php endif; ?>
                <?php if($user->id_number): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">CCCD/CMND</span>
                    <code class="small"><?php echo e($user->id_number); ?></code>
                </div>
                <?php endif; ?>
            </div>

            <?php if($profile): ?>
            <hr class="my-3">
            <h6 class="text-muted mb-2 small fw-semibold">BẰNG LÁI XE</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Số bằng</span>
                    <code class="small"><?php echo e($profile->license_number); ?></code>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Hạng bằng</span>
                    <span class="badge bg-primary">Hạng <?php echo e($profile->license_class); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Hết hạn</span>
                    <span class="small">
                        <?php echo e($profile->license_expiry->format('d/m/Y')); ?>

                        <?php if($profile->license_expiry->isPast()): ?>
                            <span class="badge bg-danger ms-1">Hết hạn</span>
                        <?php elseif($profile->license_expiry->diffInDays(now()) < 60): ?>
                            <span class="badge bg-warning text-dark ms-1">Sắp HH</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Kinh nghiệm</span>
                    <span class="small"><?php echo e($profile->experience_years); ?> năm</span>
                </div>
                <?php if($profile->operator): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Quản lý bởi</span>
                    <span class="small fw-semibold"><?php echo e($profile->operator->name); ?></span>
                </div>
                <?php endif; ?>
                <?php if($profile->notes): ?>
                <div>
                    <span class="text-muted small d-block">Ghi chú</span>
                    <div class="small text-muted"><?php echo e($profile->notes); ?></div>
                </div>
                <?php endif; ?>
            </div>

            
            <hr class="my-3">
            <h6 class="text-muted mb-2 small fw-semibold">HỒ SƠ & GIẤY TỜ</h6>

            
            <div class="mb-2">
                <div class="small text-muted mb-1">Căn cước công dân</div>
                <div class="d-flex gap-2">
                    <?php $__currentLoopData = ['photo_id_card' => 'Mặt trước', 'photo_id_card_back' => 'Mặt sau']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($profile->{$col}): ?>
                            <div>
                                <a href="<?php echo e($profile->photoUrl($col)); ?>" target="_blank">
                                    <img src="<?php echo e($profile->photoUrl($col)); ?>" alt="<?php echo e($lbl); ?>"
                                         class="rounded border object-fit-cover" style="width:88px;height:56px;"
                                         title="<?php echo e($lbl); ?>">
                                </a>
                                <div class="text-muted" style="font-size:.65rem;text-align:center;"><?php echo e($lbl); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="rounded border bg-light d-flex flex-column align-items-center justify-content-center text-muted"
                                 style="width:88px;height:56px;font-size:.65rem;"><?php echo e($lbl); ?></div>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            
            <?php $vehicleUrls = $profile->vehiclePhotoUrls(); ?>
            <?php if(count($vehicleUrls)): ?>
            <div>
                <div class="small text-muted mb-1">Ảnh xe (<?php echo e(count($vehicleUrls)); ?>)</div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php $__currentLoopData = $vehicleUrls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e($url); ?>" target="_blank">
                            <img src="<?php echo e($url); ?>" alt="Xe <?php echo e($i+1); ?>"
                                 class="rounded border object-fit-cover" style="width:72px;height:48px;">
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-warning mt-3 mb-0 py-2 small">
                Chưa có hồ sơ tài xế. Liên hệ quản lý để được cập nhật.
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
                                <strong><?php echo e($s->vehicle->capacity - $s->available_seats); ?></strong>
                                <span class="text-muted">/ <?php echo e($s->vehicle->capacity); ?> ghế</span>
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
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views\driver\dashboard.blade.php ENDPATH**/ ?>