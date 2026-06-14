<div class="alert alert-light border small mb-3">
    Đăng ký khách hàng để đặt vé, theo dõi chuyến đi và quản lý vé của bạn.
</div>

<h6 class="text-muted border-bottom pb-2 mb-3">Thông tin đăng nhập</h6>
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
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
        <input type="tel" name="phone" value="{{ old('phone') }}"
               class="form-control @error('phone') is-invalid @enderror" required placeholder="0901234567">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">CCCD / CMND</label>
        <input type="text" name="id_number" value="{{ old('id_number') }}"
               class="form-control @error('id_number') is-invalid @enderror" placeholder="012345678901">
        @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Ngày sinh</label>
        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
               class="form-control @error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Địa chỉ liên hệ</label>
        <input type="text" name="address" value="{{ old('address') }}"
               class="form-control @error('address') is-invalid @enderror" placeholder="Quận, TP...">
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
