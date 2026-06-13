<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VinaRoute</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f4ff; color: #212529; }
        .navbar-brand { font-weight: 800; letter-spacing: -.5px; font-size: 1.3rem; }
        .card { border-radius: 1rem; border: none; }
        .form-control, .form-select {
            background-color: #fff;
            color: #212529;
            border: 1px solid #d0d7e6;
        }
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            color: #212529;
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.18);
        }
        .form-control::placeholder { color: #8898aa; }
        .card-title-bar { border-left: 4px solid #0d6efd; padding-left: .75rem; }
        /* Active nav link */
        .nav-link.active-page {
            color: #0d6efd !important;
            font-weight: 600;
            border-bottom: 2px solid #0d6efd;
        }
        .navbar { min-height: 58px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand text-primary" href="<?php echo e(url('/')); ?>">VinaRoute</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                <?php if(auth()->guard()->check()): ?>
                    <?php $role = auth()->user()->role; ?>

                    
                    <?php if($role === 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('customer.dashboard') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('customer.dashboard')); ?>">Đặt vé</a>
                        </li>
                    <?php endif; ?>

                    
                    <?php if($role === 'driver'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('driver.dashboard') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('driver.dashboard')); ?>">Lịch của tôi</a>
                        </li>
                    <?php endif; ?>

                    
                    <?php if($role === 'operator'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('operator.dashboard') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('operator.dashboard')); ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('operator.drivers*') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('operator.drivers')); ?>">Quản lý tài xế</a>
                        </li>
                    <?php endif; ?>

                    
                    <?php if($role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('admin.dashboard') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('admin.dashboard')); ?>">Quản trị</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('admin.bookings') ? 'active-page' : ''); ?>"
                               href="<?php echo e(route('admin.bookings')); ?>">Booking</a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <span class="nav-link text-muted small pe-0">
                            <?php echo e(auth()->user()->name); ?>

                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;vertical-align:middle;">
                                <?php echo e(match(auth()->user()->role) {
                                    'admin'    => 'Quản trị',
                                    'operator' => 'Quản lý',
                                    'driver'   => 'Tài xế',
                                    default    => 'Khách hàng',
                                }); ?>

                            </span>
                        </span>
                    </li>
                    <li class="nav-item ms-1">
                        <form action="<?php echo e(route('logout')); ?>" method="POST" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-sm btn-outline-primary">Đăng xuất</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo e(route('login')); ?>">Đăng nhập</a></li>
                    <li class="nav-item ms-1">
                        <a class="btn btn-primary btn-sm" href="<?php echo e(route('register')); ?>">Đăng ký</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php echo $__env->make('partials.alerts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->yieldContent('content'); ?>
</div>

<footer class="bg-dark text-secondary mt-5 py-4 border-top">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-2">VinaRoute</h6>
                <p class="small mb-0">Nền tảng đặt vé xe khách liên tỉnh cao cấp.</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-2">Tài khoản</h6>
                <?php if(auth()->guard()->guest()): ?>
                    <p class="small mb-1"><a href="<?php echo e(route('login')); ?>" class="text-secondary text-decoration-none">Đăng nhập</a></p>
                    <p class="small mb-0"><a href="<?php echo e(route('register')); ?>" class="text-secondary text-decoration-none">Đăng ký</a></p>
                <?php else: ?>
                    <p class="small mb-1 text-white"><?php echo e(auth()->user()->name); ?></p>
                    <form action="<?php echo e(route('logout')); ?>" method="POST" class="d-inline">
                        <?php echo csrf_field(); ?>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.8rem;">Đăng xuất</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-2">Liên hệ</h6>
                <p class="small mb-1">Hotline: 1900-881-99</p>
                <p class="small mb-0">Email: support@vinaroute.vn</p>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">© 2026 VinaRoute. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Working\appdatxe\server\resources\views\layouts\app.blade.php ENDPATH**/ ?>