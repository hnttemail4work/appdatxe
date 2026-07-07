{{-- Trường form tài xế — dùng chung create/edit quản lý --}}
@php
    $isEdit = $mode === 'edit';
    $user = $driver?->user;
    $readonly = $readonly ?? false;
    $contactLocked = ! $readonly && $isEdit && $driver?->contactFieldsLocked();
    $ro = $readonly ? 'readonly disabled' : '';
    $profileEmail = old('email');
    if ($profileEmail === null && $user) {
        $profileEmail = $user->emailForForm();
    }
@endphp

<div class="row g-3">
    <div class="col-12"><h6 class="text-muted mb-0">Liên hệ</h6></div>

    @if($isEdit && filled($driver?->driver_code))
    <div class="col-md-6">
        <label class="form-label">Mã tài xế</label>
        <input type="text" class="form-control driver-field-locked" value="{{ $driver->driver_code }}" readonly disabled tabindex="-1" aria-readonly="true">
    </div>
    @endif

    <div class="col-md-6">
        <label class="form-label">Họ và tên @if(! $readonly && ! $contactLocked)<span class="text-danger">*</span>@endif</label>
        @if($readonly || $contactLocked)
        <input type="text" class="form-control driver-field-locked"
               value="{{ $user->name ?? '' }}" readonly disabled tabindex="-1" aria-readonly="true">
        @else
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @endif
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Số điện thoại @if(! $readonly && ! $contactLocked)<span class="text-danger">*</span>@endif</label>
        @if($readonly || $contactLocked)
        <input type="text" class="form-control driver-field-locked"
               value="{{ $user->phone ?? '' }}" readonly disabled tabindex="-1" aria-readonly="true">
        @else
        <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror" required>
        @endif
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Email</label>
        @if($readonly)
        <input type="email" class="form-control driver-field-locked"
               value="{{ $profileEmail ?? '' }}" readonly disabled tabindex="-1" aria-readonly="true">
        @else
        <input type="email" name="email" value="{{ $profileEmail ?? '' }}"
               class="form-control @error('email') is-invalid @enderror" autocomplete="off">
        @endif
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if(! $readonly && ! $isEdit)
    <div class="col-md-6">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password"
               class="form-control @error('password') is-invalid @enderror"
               placeholder="Tối thiểu 8 ký tự"
               autocomplete="off" spellcheck="false">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @elseif($isEdit && ! $readonly && ($driver?->isOperational() ?? false))
    <div class="col-12">
        @include('partials.driver-password-reset-admin', [
            'driver' => $driver,
            'canReset' => true,
        ])
    </div>
    @endif

    <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Thông tin xe</h6></div>

    <div class="col-md-4">
        <label class="form-label">Biển số xe</label>
        <input type="text" name="vehicle_license_plate"
               value="{{ old('vehicle_license_plate', $driver?->vehicle_license_plate ?? '') }}"
               class="form-control @error('vehicle_license_plate') is-invalid @enderror"
               placeholder="51A-12345" {{ $ro }}>
        @error('vehicle_license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Loại xe</label>
        <select name="vehicle_type" class="form-select @error('vehicle_type') is-invalid @enderror" {{ $readonly ? 'disabled' : '' }}>
            <option value="">-- Chọn --</option>
            @foreach(['limousine' => 'Limousine', 'sedan' => 'Sedan', 'suv' => 'SUV'] as $val => $lbl)
                <option value="{{ $val }}" {{ old('vehicle_type', $driver?->vehicle_type) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
        </select>
        @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Số ghế</label>
        <input type="number" name="vehicle_seats" min="4" max="50"
               value="{{ old('vehicle_seats', $driver?->vehicle_seats ?? '') }}"
               class="form-control @error('vehicle_seats') is-invalid @enderror" {{ $ro }}>
        @error('vehicle_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12"><hr class="my-1"><h6 class="text-muted mb-0">Thông tin ngân hàng</h6></div>

    <div class="col-md-6">
        <label class="form-label">Tên ngân hàng @if(! $readonly)<span class="text-danger">*</span>@endif</label>
        @php
            $bankOptions = ['Vietcombank', 'Techcombank', 'BIDV', 'VietinBank', 'MB Bank', 'ACB', 'Sacombank', 'TPBank', 'VPBank'];
            $currentBank = old('bank_name', $driver?->bank_name ?? '');
        @endphp
        <select name="bank_name" class="form-select @error('bank_name') is-invalid @enderror" {{ $readonly ? 'disabled' : '' }} @if(! $readonly) required @endif>
            <option value="">-- Chọn ngân hàng --</option>
            @foreach($bankOptions as $bank)
                <option value="{{ $bank }}" {{ $currentBank === $bank ? 'selected' : '' }}>{{ $bank }}</option>
            @endforeach
            @if($currentBank && ! in_array($currentBank, $bankOptions, true))
                <option value="{{ $currentBank }}" selected>{{ $currentBank }}</option>
            @endif
        </select>
        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Số tài khoản @if(! $readonly)<span class="text-danger">*</span>@endif</label>
        <input type="text" name="bank_account"
               value="{{ old('bank_account', $driver?->bank_account ?? '') }}"
               class="form-control @error('bank_account') is-invalid @enderror"
               placeholder="VD: 0123456789" {{ $ro }} @if(! $readonly) required @endif>
        @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
