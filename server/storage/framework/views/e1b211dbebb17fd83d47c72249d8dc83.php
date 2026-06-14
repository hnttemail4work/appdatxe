<?php $__env->startSection('content'); ?>
<div class="hero-section rounded-4 mb-4" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: #fff; padding: 60px 40px 80px;">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <h1 class="mb-3" style="font-size:2.2rem; font-weight:800; line-height:1.2;">
                Đặt vé xe limousine &amp; ghế VIP liên tỉnh dễ dàng
            </h1>
            <p class="mb-4" style="font-size:1.05rem; opacity:.9;">
                Tìm chuyến, chọn ghế, đặt vé — xác nhận trong vài phút. Hệ thống quản lý toàn diện cho khách hàng, tài xế và quản trị viên.
            </p>
            <?php if(auth()->guard()->guest()): ?>
                <a href="<?php echo e(route('login')); ?>" class="btn btn-light text-primary fw-bold me-2">Bắt đầu đặt vé</a>
                <a href="<?php echo e(route('register', ['mode' => 'customer'])); ?>" class="btn btn-outline-light">Đăng ký khách hàng</a>
                <a href="<?php echo e(route('register', ['mode' => 'driver'])); ?>" class="btn btn-outline-light ms-2">Đăng ký tài xế</a>
            <?php else: ?>
                <?php $role = auth()->user()->role; ?>
                <?php if($role === 'customer'): ?>
                    <a href="<?php echo e(route('customer.dashboard')); ?>" class="btn btn-light text-primary fw-bold">Đặt vé ngay</a>
                <?php elseif($role === 'operator'): ?>
                    <a href="<?php echo e(route('operator.dashboard')); ?>" class="btn btn-light text-primary fw-bold">Vào Dashboard</a>
                <?php elseif($role === 'driver'): ?>
                    <a href="<?php echo e(route('driver.dashboard')); ?>" class="btn btn-light text-primary fw-bold">Xem lịch của tôi</a>
                <?php elseif($role === 'admin'): ?>
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="btn btn-light text-primary fw-bold">Quản trị hệ thống</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="col-lg-6 d-none d-lg-block text-center">
            <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80"
                 alt="Xe VIP" class="img-fluid rounded-4" style="max-height:260px; object-fit:cover;">
        </div>
    </div>
</div>


<div class="card shadow-sm p-4 mb-5" style="margin-top: -40px; position:relative; z-index:10;">
    <h5 class="fw-bold mb-4">Tìm chuyến xe</h5>
    <form action="<?php echo e(auth()->check() ? route('trips.search') : route('login')); ?>" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Điểm đi</label>
                <select name="departure" class="form-select">
                    <option value="">-- Chọn điểm đi --</option>
                    <?php $__currentLoopData = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($p); ?>"><?php echo e($p); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Điểm đến</label>
                <select name="destination" class="form-select">
                    <option value="">-- Chọn điểm đến --</option>
                    <?php $__currentLoopData = ['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng','Vũng Tàu','Đà Lạt','Nha Trang','Mũi Né','Huế','Quy Nhơn','Buôn Ma Thuột']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($p); ?>"><?php echo e($p); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ngày đi</label>
                <input type="date" name="date" class="form-control" id="homeDate">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Tìm chuyến</button>
            </div>
        </div>
        <?php if(auth()->guard()->guest()): ?>
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                Chưa có tài khoản? <a href="<?php echo e(route('register')); ?>">Đăng ký</a> để đặt vé.
                Đã có? <a href="<?php echo e(route('login')); ?>">Đăng nhập</a>.
            </p>
        <?php endif; ?>
    </form>
</div>


<div class="row g-4 mb-5">
    <div class="col-12"><h4 class="fw-bold">Tuyến phổ biến</h4></div>
    <?php $__currentLoopData = [
        ['TP.HCM → Vũng Tàu', '200.000 đ', 'Limousine VIP · ~2 giờ · Hàng ngày'],
        ['TP.HCM → Đà Lạt',   '350.000 đ', 'Limousine VIP · ~7 giờ · Ban đêm'],
        ['TP.HCM → Mũi Né',   '280.000 đ', 'Sedan · ~4 giờ · Hàng ngày'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route, $price, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="col-md-4">
        <div class="card p-4 h-100 border shadow-sm" style="border-radius:1rem;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <strong><?php echo e($route); ?></strong>
                <span class="badge bg-primary"><?php echo e($price); ?></span>
            </div>
            <p class="text-muted mb-3" style="font-size:.9rem;"><?php echo e($desc); ?></p>
            <a href="<?php echo e(auth()->check() ? route('dashboard') : route('login')); ?>"
               class="btn btn-sm btn-outline-primary mt-auto">Đặt vé</a>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div class="row g-4 mb-5">
    <div class="col-12"><h4 class="fw-bold">Tại sao chọn <?php echo e(config('app.name')); ?>?</h4></div>
    <?php $__currentLoopData = [
        ['🪑', 'Chọn ghế linh hoạt', 'Chọn đúng ghế bạn muốn, hệ thống giữ ghế 15 phút trong khi thanh toán.'],
        ['💳', 'Thanh toán đơn giản', 'Xác nhận đặt vé và thanh toán toàn bộ, nhận mã vé ngay lập tức.'],
        ['🔔', 'Quản lý đơn dễ dàng', 'Xem, xác nhận hoặc hủy vé bất kỳ lúc nào qua tài khoản cá nhân.'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="col-md-4 text-center">
        <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
            <div class="fs-2 mb-2"><?php echo e($icon); ?></div>
            <h6 class="fw-bold"><?php echo e($title); ?></h6>
            <p class="text-muted small mb-0"><?php echo e($desc); ?></p>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const d = document.getElementById('homeDate');
    if (d) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        d.value = tomorrow.toISOString().split('T')[0];
    }
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views/home.blade.php ENDPATH**/ ?>