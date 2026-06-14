{{-- Trường form tài xế — dùng chung create/edit --}}
@php
    $isEdit = $mode === 'edit';
    $user = $driver?->user;
@endphp

<div class="row g-3">
    <div class="col-12"><h6 class="text-muted mb-0">Tài khoản đăng nhập</h6></div>

    <div class="col-md-6">
        <label class="form-label">Vai trò</label>
        <input type="text" class="form-control bg-light" value="Tài xế" readonly disabled>
    </div>

    <div class="col-md-6">
        @if($isEdit && $driver?->driver_code)
        <label class="form-label">Mã tài xế</label>
        <input type="text" class="form-control bg-light" value="{{ $driver->driver_code }}" readonly disabled>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
        <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror" required>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">
            Mật khẩu
            @if($isEdit)
                <span class="text-muted small">(để trống nếu không đổi)</span>
            @else
                <span class="text-danger">*</span>
            @endif
        </label>
        <input type="password" name="password"
               class="form-control @error('password') is-invalid @enderror"
               {{ $isEdit ? '' : 'required' }}
               placeholder="{{ $isEdit ? 'Nhập mật khẩu mới...' : 'Tối thiểu 8 ký tự' }}">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">CCCD / CMND</label>
        <input type="text" name="id_number" value="{{ old('id_number', $user->id_number ?? '') }}"
               class="form-control @error('id_number') is-invalid @enderror" placeholder="012345678901">
        @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Địa chỉ</label>
        <input type="text" name="address" value="{{ old('address', $user->address ?? '') }}"
               class="form-control @error('address') is-invalid @enderror">
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if(auth()->user()->role === 'admin')
    <div class="col-md-6">
        <label class="form-label">Đơn vị vận hành <span class="text-danger">*</span></label>
        <select name="operator_id" class="form-select @error('operator_id') is-invalid @enderror"
                {{ $isEdit ? '' : 'required' }}>
            <option value="">-- Chọn đơn vị --</option>
            @foreach($operators as $op)
                <option value="{{ $op->id }}"
                    {{ (string) old('operator_id', $driver?->operator_id ?? '') === (string) $op->id ? 'selected' : '' }}>
                    {{ $op->name }}
                </option>
            @endforeach
        </select>
        @error('operator_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endif

    <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Bằng lái xe</h6></div>

    <div class="col-md-4">
        <label class="form-label">Số bằng lái</label>
        <input type="text" name="license_number"
               value="{{ old('license_number', $driver?->license_number ?? '') }}"
               class="form-control @error('license_number') is-invalid @enderror">
        @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">Hạng bằng</label>
        <select name="license_class" class="form-select @error('license_class') is-invalid @enderror">
            @foreach(['B1','B2','C','D','E','F'] as $cls)
                <option value="{{ $cls }}"
                    {{ old('license_class', $driver?->license_class ?? 'B2') === $cls ? 'selected' : '' }}>
                    {{ $cls }}
                </option>
            @endforeach
        </select>
        @error('license_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Ngày hết hạn bằng</label>
        <input type="date" name="license_expiry"
               value="{{ old('license_expiry', $driver?->license_expiry?->format('Y-m-d') ?? '') }}"
               class="form-control @error('license_expiry') is-invalid @enderror">
        @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Kinh nghiệm (năm)</label>
        <input type="number" name="experience_years" min="0" max="50"
               value="{{ old('experience_years', $driver?->experience_years ?? 0) }}"
               class="form-control @error('experience_years') is-invalid @enderror">
        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if($isEdit)
    <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Trạng thái</h6></div>

    <div class="col-md-4">
        <label class="form-label">Trạng thái tài khoản</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror">
            @foreach(['active','inactive','suspended'] as $st)
                <option value="{{ $st }}" {{ old('status', $driver?->status) === $st ? 'selected' : '' }}>
                    {{ match($st) { 'active'=>'Hoạt động','inactive'=>'Không hoạt động','suspended'=>'Tạm ngưng' } }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Trạng thái làm việc</label>
        <select name="availability_status" class="form-select @error('availability_status') is-invalid @enderror">
            @foreach(['available' => 'Sẵn sàng', 'on_trip' => 'Đang chạy', 'off_duty' => 'Nghỉ'] as $val => $lbl)
                <option value="{{ $val }}"
                    {{ old('availability_status', $driver?->availability_status ?? 'off_duty') === $val ? 'selected' : '' }}>
                    {{ $lbl }}
                </option>
            @endforeach
        </select>
        @error('availability_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endif

    <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Thông tin ngân hàng</h6></div>

    <div class="col-md-6">
        <label class="form-label">Tên ngân hàng</label>
        <input type="text" name="bank_name"
               value="{{ old('bank_name', $driver?->bank_name ?? '') }}"
               class="form-control @error('bank_name') is-invalid @enderror"
               placeholder="VD: Vietcombank, Techcombank...">
        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Số tài khoản ngân hàng</label>
        <input type="text" name="bank_account"
               value="{{ old('bank_account', $driver?->bank_account ?? '') }}"
               class="form-control @error('bank_account') is-invalid @enderror"
               placeholder="VD: 0123456789">
        @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Ghi chú</label>
        <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $driver?->notes ?? '') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
