<?php $__env->startSection('content'); ?>
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0 card-title-bar">Quản lý tài xế</h3>
            <p class="text-muted mb-0">Thêm và cập nhật thông tin tài xế.</p>
        </div>
        <a href="<?php echo e(route('operator.dashboard')); ?>" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
    </div>

    <?php if(auth()->user()->role === 'operator'): ?>
    <div class="col-lg-5">
        <div class="card shadow-sm p-4">
            <h5>Thêm tài xế mới</h5>
            <form method="POST" action="<?php echo e(route('operator.drivers.store')); ?>" class="mt-3">
                <?php echo csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="<?php echo e(old('name')); ?>" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="<?php echo e(old('email')); ?>" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" value="<?php echo e(old('phone')); ?>" class="form-control <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required placeholder="Tối thiểu 8 ký tự">
                        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CCCD/CMND</label>
                        <input type="text" name="id_number" value="<?php echo e(old('id_number')); ?>" class="form-control" placeholder="012345678901">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Địa chỉ</label>
                        <input type="text" name="address" value="<?php echo e(old('address')); ?>" class="form-control">
                    </div>
                    <div class="col-12"><hr class="my-1"><strong class="small text-muted">THÔNG TIN BẰNG LÁI</strong></div>
                    <div class="col-md-6">
                        <label class="form-label">Số bằng lái <span class="text-danger">*</span></label>
                        <input type="text" name="license_number" value="<?php echo e(old('license_number')); ?>" class="form-control <?php $__errorArgs = ['license_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['license_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hạng bằng <span class="text-danger">*</span></label>
                        <select name="license_class" class="form-select" required>
                            <?php $__currentLoopData = ['B1','B2','C','D','E','F']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cls): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($cls); ?>" <?php echo e(old('license_class','B2') === $cls ? 'selected' : ''); ?>><?php echo e($cls); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày hết hạn bằng <span class="text-danger">*</span></label>
                        <input type="date" name="license_expiry" value="<?php echo e(old('license_expiry')); ?>" class="form-control <?php $__errorArgs = ['license_expiry'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['license_expiry'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kinh nghiệm (năm)</label>
                        <input type="number" name="experience_years" value="<?php echo e(old('experience_years', 0)); ?>" min="0" max="50" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="notes" class="form-control" rows="2"><?php echo e(old('notes')); ?></textarea>
                    </div>
                </div>
                <button class="btn btn-primary w-100 mt-3">Thêm tài xế</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo e(auth()->user()->role === 'operator' ? 'col-lg-7' : 'col-12'); ?>">
        <div class="card shadow-sm p-4">
            <h5>Danh sách tài xế (<?php echo e($drivers->count()); ?>)</h5>
            <?php if($drivers->isEmpty()): ?>
                <p class="text-muted mt-3">Chưa có tài xế nào.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-4 mt-3">
                <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $availLabel = match($d->availability_status ?? 'off_duty') {
                        'available' => ['Sẵn sàng', 'success'],
                        'on_trip'   => ['Đang chạy', 'primary'],
                        default     => ['Nghỉ', 'secondary'],
                    };
                    $vehicleUrls = $d->vehiclePhotoUrls();
                ?>
                <div class="border rounded-3 p-3">
                    
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <div class="flex-shrink-0">
                            <?php if($d->photo_portrait): ?>
                                <img src="<?php echo e($d->photoUrl('photo_portrait')); ?>" alt="Chân dung"
                                     class="rounded-circle object-fit-cover" style="width:52px;height:52px;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                     style="width:52px;height:52px;font-size:1.2rem;font-weight:700;">
                                    <?php echo e(mb_substr($d->user->name, 0, 1)); ?>

                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div>
                                <strong><?php echo e($d->user->name); ?></strong>
                                <span class="badge bg-<?php echo e(match($d->status) { 'active'=>'primary','suspended'=>'danger',default=>'secondary' }); ?> ms-1">
                                    <?php echo e(match($d->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' }); ?>

                                </span>
                                <span class="badge bg-<?php echo e($availLabel[1]); ?> ms-1"><?php echo e($availLabel[0]); ?></span>
                                <?php if($d->operator && auth()->user()->role === 'admin'): ?>
                                    <span class="badge bg-light text-dark border ms-1"><?php echo e($d->operator->name); ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo e($d->user->phone); ?> · <?php echo e($d->user->email); ?></small>
                            <div class="mt-1 small text-muted">
                                Hạng <strong><?php echo e($d->license_class); ?></strong> ·
                                HH: <?php echo e($d->license_expiry->format('d/m/Y')); ?>

                                <?php if($d->license_expiry->isPast()): ?>
                                    <span class="badge bg-danger">Hết hạn</span>
                                <?php elseif($d->license_expiry->diffInDays(now()) < 60): ?>
                                    <span class="badge bg-warning text-dark">Sắp HH</span>
                                <?php endif; ?>
                                · <?php echo e($d->experience_years); ?> năm KN
                            </div>
                        </div>
                    </div>

                    
                    <div class="mb-2">
                        <small class="text-muted fw-semibold d-block mb-1">CCCD / Căn cước</small>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php $__currentLoopData = ['photo_id_card' => 'Mặt trước', 'photo_id_card_back' => 'Mặt sau']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if($d->{$col}): ?>
                                    <a href="<?php echo e($d->photoUrl($col)); ?>" target="_blank">
                                        <img src="<?php echo e($d->photoUrl($col)); ?>" alt="<?php echo e($lbl); ?>"
                                             class="rounded border object-fit-cover" style="width:100px;height:64px;"
                                             title="<?php echo e($lbl); ?>">
                                    </a>
                                <?php else: ?>
                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted"
                                         style="width:100px;height:64px;font-size:.72rem;"><?php echo e($lbl); ?></div>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>

                    
                    <div class="mb-2">
                        <small class="text-muted fw-semibold d-block mb-1">Chân dung</small>
                        <div class="d-flex gap-2">
                            <?php if($d->photo_portrait): ?>
                                <a href="<?php echo e($d->photoUrl('photo_portrait')); ?>" target="_blank">
                                    <img src="<?php echo e($d->photoUrl('photo_portrait')); ?>" alt="Chân dung"
                                         class="rounded border object-fit-cover" style="width:64px;height:80px;">
                                </a>
                            <?php else: ?>
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted"
                                     style="width:64px;height:80px;font-size:.72rem;">Chân dung</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <div class="mb-3">
                        <small class="text-muted fw-semibold d-block mb-1">Ảnh xe (<?php echo e(count($vehicleUrls)); ?>)</small>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php $__empty_1 = true; $__currentLoopData = $vehicleUrls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <div class="position-relative">
                                    <a href="<?php echo e($url); ?>" target="_blank">
                                        <img src="<?php echo e($url); ?>" alt="Xe <?php echo e($idx+1); ?>"
                                             class="rounded border object-fit-cover" style="width:80px;height:56px;">
                                    </a>
                                    <form method="POST" action="<?php echo e(route('operator.drivers.photos', $d)); ?>"
                                          class="d-inline" style="position:absolute;top:-6px;right:-6px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_vehicle_idx" value="<?php echo e($idx); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm p-0 lh-1"
                                                style="width:18px;height:18px;font-size:.65rem;"
                                                onclick="return confirm('Xóa ảnh này?')" title="Xóa">×</button>
                                    </form>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="text-muted small">Chưa có ảnh xe.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <form method="POST" action="<?php echo e(route('operator.drivers.photos', $d)); ?>"
                          enctype="multipart/form-data" class="border rounded p-2 bg-light mb-3">
                        <?php echo csrf_field(); ?>
                        <small class="fw-semibold text-muted d-block mb-2">Upload / cập nhật ảnh</small>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">Chân dung</label>
                                <input type="file" name="photo_portrait" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt trước</label>
                                <input type="file" name="photo_id_card" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt sau</label>
                                <input type="file" name="photo_id_card_back" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-0" style="font-size:.75rem;">Thêm ảnh xe (chọn nhiều)</label>
                                <input type="file" name="photo_vehicles[]" accept="image/*" multiple class="form-control form-control-sm">
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary mt-2">Upload ảnh</button>
                    </form>

                    
                    <form method="POST" action="<?php echo e(route('operator.drivers.update', $d)); ?>"
                          class="d-flex gap-2 flex-wrap align-items-center mb-2">
                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                        <select name="status" class="form-select form-select-sm" style="width:130px">
                            <?php $__currentLoopData = ['active','inactive','suspended']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($st); ?>" <?php echo e($d->status === $st ? 'selected' : ''); ?>>
                                    <?php echo e(match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' }); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <select name="availability_status" class="form-select form-select-sm" style="width:130px">
                            <option value="available" <?php echo e(($d->availability_status ?? '') === 'available' ? 'selected' : ''); ?>>Sẵn sàng</option>
                            <option value="on_trip"   <?php echo e(($d->availability_status ?? '') === 'on_trip'   ? 'selected' : ''); ?>>Đang chạy</option>
                            <option value="off_duty"  <?php echo e(($d->availability_status ?? 'off_duty') === 'off_duty'  ? 'selected' : ''); ?>>Nghỉ</option>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Lưu trạng thái</button>
                    </form>

                    
                    <button class="btn btn-sm btn-link text-muted p-0 mb-1"
                            data-bs-toggle="collapse"
                            data-bs-target="#edit-info-<?php echo e($d->id); ?>">Sửa thông tin chi tiết ▾</button>
                    <div class="collapse" id="edit-info-<?php echo e($d->id); ?>">
                        <form method="POST" action="<?php echo e(route('operator.drivers.update', $d)); ?>"
                              class="border rounded p-3 bg-white mt-1">
                            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                            <div class="row g-2">
                                <div class="col-12"><small class="fw-semibold text-muted">Thông tin cá nhân</small></div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">Họ và tên</label>
                                    <input type="text" name="name" value="<?php echo e($d->user->name); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">Số điện thoại</label>
                                    <input type="tel" name="phone" value="<?php echo e($d->user->phone); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">CCCD/CMND</label>
                                    <input type="text" name="id_number" value="<?php echo e($d->user->id_number); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-0 small">Địa chỉ</label>
                                    <input type="text" name="address" value="<?php echo e($d->user->address); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 mt-2"><small class="fw-semibold text-muted">Bằng lái xe</small></div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small">Số bằng lái</label>
                                    <input type="text" name="license_number" value="<?php echo e($d->license_number); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0 small">Hạng bằng</label>
                                    <select name="license_class" class="form-select form-select-sm">
                                        <?php $__currentLoopData = ['B1','B2','C','D','E','F']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cls): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($cls); ?>" <?php echo e($d->license_class === $cls ? 'selected' : ''); ?>><?php echo e($cls); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small">Hết hạn bằng</label>
                                    <input type="date" name="license_expiry" value="<?php echo e($d->license_expiry->format('Y-m-d')); ?>" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0 small">Kinh nghiệm (năm)</label>
                                    <input type="number" name="experience_years" value="<?php echo e($d->experience_years); ?>" min="0" max="50" class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-0 small">Ghi chú</label>
                                    <textarea name="notes" class="form-control form-control-sm" rows="2"><?php echo e($d->notes); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-sm btn-primary">Lưu thông tin</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views\operator\drivers.blade.php ENDPATH**/ ?>