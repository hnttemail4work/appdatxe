
<?php
    $maxMb = 2;
?>
<form method="POST" action="<?php echo e($action); ?>" enctype="multipart/form-data"
      class="border rounded p-2 bg-light mb-3 driver-photo-form">
    <?php echo csrf_field(); ?>
    <small class="fw-semibold text-muted d-block mb-2"><?php echo e($title ?? 'Upload / cập nhật ảnh hồ sơ'); ?></small>
    <p class="text-muted mb-2" style="font-size:.75rem;">
        Chọn ảnh cần thay đổi (bỏ trống ảnh không đổi). JPG, PNG, WebP — tối đa <?php echo e($maxMb); ?>MB/ảnh.
    </p>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">Chân dung</label>
            <input type="file" name="photo_portrait" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm <?php $__errorArgs = ['photo_portrait'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   data-max-bytes="<?php echo e($maxMb * 1024 * 1024); ?>">
            <?php $__errorArgs = ['photo_portrait'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt trước</label>
            <input type="file" name="photo_id_card" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm <?php $__errorArgs = ['photo_id_card'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   data-max-bytes="<?php echo e($maxMb * 1024 * 1024); ?>">
            <?php $__errorArgs = ['photo_id_card'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="col-md-4">
            <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt sau</label>
            <input type="file" name="photo_id_card_back" accept="image/jpeg,image/png,image/webp"
                   class="form-control form-control-sm <?php $__errorArgs = ['photo_id_card_back'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   data-max-bytes="<?php echo e($maxMb * 1024 * 1024); ?>">
            <?php $__errorArgs = ['photo_id_card_back'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="col-12">
            <label class="form-label mb-0" style="font-size:.75rem;">Thêm ảnh xe (có thể chọn nhiều)</label>
            <input type="file" name="photo_vehicles[]" accept="image/jpeg,image/png,image/webp" multiple
                   class="form-control form-control-sm <?php $__errorArgs = ['photo_vehicles.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php $__errorArgs = ['photo_vehicles.0'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   data-max-bytes="<?php echo e($maxMb * 1024 * 1024); ?>">
            <?php $__errorArgs = ['photo_vehicles.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['photo_vehicles.0'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
    </div>
    <button class="btn btn-sm btn-primary mt-2"><?php echo e($submitLabel ?? 'Lưu ảnh'); ?></button>
</form>

<?php if (! $__env->hasRenderedOnce('ae26fd8e-0fed-474a-8a9b-461a5e92c45d')): $__env->markAsRenderedOnce('ae26fd8e-0fed-474a-8a9b-461a5e92c45d'); ?>
    <?php $__env->startPush('scripts'); ?>
    <script>
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-max-bytes]')) {
            return;
        }
        var max = parseInt(input.dataset.maxBytes, 10);
        var oversize = [];
        Array.from(input.files || []).forEach(function (file) {
            if (file.size > max) {
                oversize.push(file.name);
            }
        });
        if (oversize.length) {
            alert('Ảnh vượt quá 2MB: ' + oversize.join(', ') + '\nVui lòng chọn ảnh nhỏ hơn.');
            input.value = '';
        }
    });
    </script>
    <?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH C:\Working\appdatxe\server\resources\views/partials/driver-photo-upload-form.blade.php ENDPATH**/ ?>