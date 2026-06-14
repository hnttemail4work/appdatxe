<?php $__env->startSection('content'); ?>
<?php
    $mode = $mode ?? old('register_mode', 'customer');
    if (! in_array($mode, ['customer', 'driver'], true)) {
        $mode = 'customer';
    }
?>
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="mb-1">Đăng ký tài khoản</h2>
                    <p class="text-muted mb-0">Chọn loại tài khoản và điền đầy đủ thông tin.</p>
                </div>
                <a href="<?php echo e(route('login')); ?>" class="btn btn-sm btn-outline-secondary">Đã có tài khoản? Đăng nhập</a>
            </div>

            <ul class="nav nav-pills nav-fill mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo e($mode === 'customer' ? 'active' : ''); ?>"
                       href="<?php echo e(route('register', ['mode' => 'customer'])); ?>">Khách hàng</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo e($mode === 'driver' ? 'active' : ''); ?>"
                       href="<?php echo e(route('register', ['mode' => 'driver'])); ?>">Tài xế</a>
                </li>
            </ul>

            <form method="POST" action="<?php echo e(route('register')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="register_mode" value="<?php echo e($mode); ?>">

                <?php if($mode === 'driver'): ?>
                    <?php echo $__env->make('auth.partials.register-driver', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php else: ?>
                    <?php echo $__env->make('auth.partials.register-customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?>

                <div class="form-check mt-3">
                    <input class="form-check-input <?php $__errorArgs = ['terms'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" type="checkbox"
                           name="terms" value="1" id="termsCheck" <?php echo e(old('terms') ? 'checked' : ''); ?> required>
                    <label class="form-check-label small" for="termsCheck">
                        Tôi đồng ý với điều khoản sử dụng và chính sách bảo mật của <?php echo e(config('app.name')); ?>.
                    </label>
                    <?php $__errorArgs = ['terms'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <button class="btn btn-primary w-100 mt-3">
                    <?php echo e($mode === 'driver' ? 'Gửi hồ sơ đăng ký tài xế' : 'Tạo tài khoản khách hàng'); ?>

                </button>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/auth/register.blade.php ENDPATH**/ ?>