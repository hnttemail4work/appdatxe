<?php $__env->startSection('content'); ?>
<?php
$categories = ['fuel' => 'Nhiên liệu', 'tire' => 'Lốp xe', 'spare_part' => 'Phụ tùng', 'other' => 'Khác'];
?>
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0 card-title-bar">Nhập xuất vật tư</h3>
            <p class="text-muted mb-0">Theo dõi nhiên liệu, lốp xe, phụ tùng cho đội xe.</p>
        </div>
        <a href="<?php echo e(route('operator.dashboard')); ?>" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
    </div>

    
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #0d6efd;">
            <div class="text-muted small mb-1">Tổng nhập</div>
            <strong class="text-primary fs-5"><?php echo e(number_format($summary['total_import'], 0, ',', '.')); ?> đ</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #dc3545;">
            <div class="text-muted small mb-1">Tổng xuất</div>
            <strong class="text-danger fs-5"><?php echo e(number_format($summary['total_export'], 0, ',', '.')); ?> đ</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm p-3 text-center" style="border-left:4px solid #6c757d;">
            <div class="text-muted small mb-1">Số giao dịch</div>
            <strong class="text-secondary fs-5"><?php echo e($items->count()); ?></strong>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h5>Thêm giao dịch</h5>
            <form method="POST" action="<?php echo e(route('operator.inventory.store')); ?>" class="mt-3">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Loại giao dịch</label>
                    <select name="type" class="form-select" required>
                        <option value="import" <?php echo e(old('type','import') === 'import' ? 'selected' : ''); ?>>Nhập vật tư</option>
                        <option value="export" <?php echo e(old('type') === 'export' ? 'selected' : ''); ?>>Xuất/Sử dụng</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Danh mục</label>
                    <select name="category" class="form-select" required>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>" <?php echo e(old('category') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên vật tư <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="<?php echo e(old('name')); ?>" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                        placeholder="vd: Dầu nhớt Castrol 10W40" required>
                    <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label">Số lượng</label>
                        <input type="number" name="quantity" value="<?php echo e(old('quantity')); ?>" step="0.01" min="0.01" class="form-control <?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                        <?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Đơn vị</label>
                        <select name="unit" class="form-select">
                            <?php $__currentLoopData = ['lít','kg','cái','bộ','m','cuộn']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($u); ?>" <?php echo e(old('unit') === $u ? 'selected' : ''); ?>><?php echo e($u); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">&nbsp;</label>
                        <input type="number" name="unit_price" value="<?php echo e(old('unit_price',0)); ?>" step="1000" min="0" class="form-control" placeholder="Giá/đơn vị">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Xe áp dụng</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">-- Tất cả / Không chọn --</option>
                        <?php $__currentLoopData = $vehicles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($v->id); ?>" <?php echo e(old('vehicle_id') == $v->id ? 'selected' : ''); ?>>
                                <?php echo e($v->license_plate); ?> · <?php echo e(ucfirst($v->type)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ngày giao dịch</label>
                    <input type="date" name="transaction_date" value="<?php echo e(old('transaction_date', now()->format('Y-m-d'))); ?>" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm..."><?php echo e(old('note')); ?></textarea>
                </div>
                <button class="btn btn-primary w-100">Lưu giao dịch</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <h5>Lịch sử giao dịch</h5>
            <?php if($items->isEmpty()): ?>
                <p class="text-muted mt-3">Chưa có giao dịch nào.</p>
            <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-borderless align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th>Tên vật tư</th>
                                <th>Danh mục</th>
                                <th>SL</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                                <th>Loại</th>
                                <th>Xe</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-bottom">
                                <td><?php echo e($item->transaction_date->format('d/m/Y')); ?></td>
                                <td><strong><?php echo e($item->name); ?></strong><?php if($item->note): ?><br><small class="text-muted"><?php echo e($item->note); ?></small><?php endif; ?></td>
                                <td><span class="badge bg-secondary"><?php echo e($categories[$item->category] ?? $item->category); ?></span></td>
                                <td><?php echo e(number_format($item->quantity, 2)); ?> <?php echo e($item->unit); ?></td>
                                <td><?php echo e(number_format($item->unit_price, 0, ',', '.')); ?></td>
                                <td><strong><?php echo e(number_format($item->total_value, 0, ',', '.')); ?> đ</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo e($item->type === 'import' ? 'success' : 'danger'); ?>">
                                        <?php echo e($item->type === 'import' ? 'Nhập' : 'Xuất'); ?>

                                    </span>
                                </td>
                                <td><?php echo e($item->vehicle?->license_plate ?? '—'); ?></td>
                                <td>
                                    <form method="POST" action="<?php echo e(route('operator.inventory.destroy', $item)); ?>"
                                        onsubmit="return confirm('Xóa bản ghi này?')">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Working\appdatxe\server\resources\views\operator\inventory.blade.php ENDPATH**/ ?>