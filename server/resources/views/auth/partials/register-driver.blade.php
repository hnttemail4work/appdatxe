<div class="alert alert-warning border small mb-3">
    Hồ sơ tài xế sẽ được <strong>quản lý/admin duyệt</strong> trước khi bạn có thể đăng nhập và nhận chuyến.
</div>

<h6 class="text-muted border-bottom pb-2 mb-3">Tài khoản đăng nhập</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name') }}"
               class="form-control @error('name') is-invalid @enderror" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" value="{{ old('email') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
        <input type="password" name="password"
               class="form-control @error('password') is-invalid @enderror" required placeholder="Tối thiểu 8 ký tự">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
</div>

<h6 class="text-muted border-bottom pb-2 mb-3">Thông tin cá nhân</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
        <input type="tel" name="phone" value="{{ old('phone') }}"
               class="form-control @error('phone') is-invalid @enderror" required>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">CCCD / CMND <span class="text-danger">*</span></label>
        <input type="text" name="id_number" value="{{ old('id_number') }}"
               class="form-control @error('id_number') is-invalid @enderror" required>
        @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Ngày sinh</label>
        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
               class="form-control @error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Địa chỉ thường trú <span class="text-danger">*</span></label>
        <input type="text" name="address" value="{{ old('address') }}"
               class="form-control @error('address') is-invalid @enderror" required>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h6 class="text-muted border-bottom pb-2 mb-3">Bằng lái xe</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Số bằng lái <span class="text-danger">*</span></label>
        <input type="text" name="license_number" value="{{ old('license_number') }}"
               class="form-control @error('license_number') is-invalid @enderror" required>
        @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Hạng bằng <span class="text-danger">*</span></label>
        <select name="license_class" class="form-select @error('license_class') is-invalid @enderror" required>
            @foreach(['B1','B2','C','D','E','F'] as $cls)
                <option value="{{ $cls }}" {{ old('license_class', 'B2') === $cls ? 'selected' : '' }}>{{ $cls }}</option>
            @endforeach
        </select>
        @error('license_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Ngày hết hạn bằng <span class="text-danger">*</span></label>
        <input type="date" name="license_expiry" value="{{ old('license_expiry') }}"
               class="form-control @error('license_expiry') is-invalid @enderror" required>
        @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Số năm kinh nghiệm</label>
        <input type="number" name="experience_years" min="0" max="50"
               value="{{ old('experience_years', 0) }}"
               class="form-control @error('experience_years') is-invalid @enderror">
        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label">Ghi chú thêm</label>
        <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror"
                  placeholder="Kinh nghiệm tuyến đường, loại xe đã chạy...">{{ old('notes') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h6 class="text-muted border-bottom pb-2 mb-3">Thông tin ngân hàng</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Tên ngân hàng</label>
        <input type="text" name="bank_name" value="{{ old('bank_name') }}"
               class="form-control @error('bank_name') is-invalid @enderror"
               placeholder="VD: Vietcombank, Techcombank...">
        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Số tài khoản ngân hàng</label>
        <input type="text" name="bank_account" value="{{ old('bank_account') }}"
               class="form-control @error('bank_account') is-invalid @enderror"
               placeholder="VD: 0123456789">
        @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<p class="text-muted small mb-0">Sau khi được duyệt, bạn đăng nhập và bổ sung ảnh hồ sơ tại mục <strong>Hồ sơ tài xế</strong>.</p>
