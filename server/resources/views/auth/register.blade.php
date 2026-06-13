@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm p-4">
            <h2 class="mb-1">Đăng ký tài khoản</h2>
            <p class="text-muted mb-4">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror"
                            placeholder="Nguyễn Văn A" required autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="ten@gmail.com" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror"
                            placeholder="Tối thiểu 8 ký tự" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation"
                            class="form-control" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}"
                            class="form-control @error('phone') is-invalid @enderror"
                            placeholder="0901234567">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                        <select name="role" id="roleSelect" class="form-select" required onchange="toggleOperatorInfo()">
                            <option value="customer" {{ old('role', 'customer') === 'customer' ? 'selected' : '' }}>Khách hàng</option>
                            <option value="operator" {{ old('role') === 'operator' ? 'selected' : '' }}>Quản lý tài xế</option>
                        </select>
                    </div>
                </div>

                {{-- Operator notice --}}
                <div id="operatorInfo" class="alert alert-primary mt-3 mb-0 py-2 small" style="display:none;">
                    Tài khoản quản lý tài xế sẽ ở trạng thái <strong>chờ duyệt</strong> cho đến khi Admin xác minh.
                </div>

                <button class="btn btn-primary w-100 mt-4">Tạo tài khoản</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleOperatorInfo() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('operatorInfo').style.display = role === 'operator' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleOperatorInfo);
</script>
@endsection
