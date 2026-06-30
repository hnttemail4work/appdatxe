{{-- Trường thông tin tài xế — wizard đăng ký --}}
@php
    use App\Support\DriverFieldRules;

    $context = 'register';
    $user = $user ?? null;
    $profile = $profile ?? null;
    $sections = $sections ?? ['contact', 'vehicle', 'bank'];
    $compact = $compact ?? false;
    $requiredFields = DriverFieldRules::requiredFieldsFor($context);
    $star = fn (string $field) => in_array($field, $requiredFields, true)
        ? '<span class="text-danger">*</span>' : '';
    $req = fn (string $field) => in_array($field, $requiredFields, true) ? 'required' : '';
    $profileEmail = old('email', '');
@endphp

@if(in_array('account', $sections, true) || in_array('contact', $sections, true))
<div class="register-section" data-field-section="account">
    <div class="register-section-title"><span class="section-icon">👤</span> Tài khoản</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Họ và tên {!! $star('name') !!}</label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}"
                   class="form-control @error('name') is-invalid @enderror" {{ $req('name') }} autocomplete="name">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Số điện thoại {!! $star('phone') !!}</label>
            <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                   class="form-control @error('phone') is-invalid @enderror" {{ $req('phone') }}
                   inputmode="tel" autocomplete="tel">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Mật khẩu {!! $star('password') !!}</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" {{ $req('password') }}
                   minlength="8" autocomplete="off" spellcheck="false">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Nhập lại mật khẩu {!! $star('password_confirmation') !!}</label>
            <input type="password" name="password_confirmation"
                   class="form-control @error('password_confirmation') is-invalid @enderror" {{ $req('password_confirmation') }}
                   minlength="8" autocomplete="off" spellcheck="false">
            @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">Email <span class="text-muted fw-normal">(tùy chọn)</span></label>
            <input type="email" name="email" value="{{ $profileEmail }}"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="off">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
@endif

@if(in_array('vehicle', $sections, true))
<div class="register-section" data-field-section="vehicle">
    <div class="register-section-title"><span class="section-icon">🚗</span> Thông tin xe</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Biển số xe {!! $star('vehicle_license_plate') !!}</label>
            <input type="text" name="vehicle_license_plate"
                   value="{{ old('vehicle_license_plate', $profile->vehicle_license_plate ?? '') }}"
                   class="form-control @error('vehicle_license_plate') is-invalid @enderror" {{ $req('vehicle_license_plate') }}
                   data-plate-format>
            @error('vehicle_license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Loại xe {!! $star('vehicle_type') !!}</label>
            <select name="vehicle_type" class="form-select @error('vehicle_type') is-invalid @enderror" {{ $req('vehicle_type') }}
                    data-seats-hint>
                <option value="">-- Chọn loại --</option>
                @foreach(['limousine' => 'Limousine', 'sedan' => 'Sedan', 'suv' => 'SUV'] as $val => $lbl)
                    <option value="{{ $val }}" data-default-seats="{{ $val === 'limousine' ? 9 : ($val === 'suv' ? 7 : 4) }}"
                        {{ old('vehicle_type', $profile->vehicle_type ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Số ghế {!! $star('vehicle_seats') !!}</label>
            <input type="number" name="vehicle_seats" min="4" max="50"
                   value="{{ old('vehicle_seats', $profile->vehicle_seats ?? 9) }}"
                   class="form-control @error('vehicle_seats') is-invalid @enderror" {{ $req('vehicle_seats') }} id="vehicle_seats_input">
            @error('vehicle_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @if(! $compact)
        <div class="col-md-4">
            <label class="form-label">Hãng xe</label>
            <input type="text" name="vehicle_brand" value="{{ old('vehicle_brand', $profile->vehicle_brand ?? '') }}"
                   class="form-control @error('vehicle_brand') is-invalid @enderror">
            @error('vehicle_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Dòng xe / Model</label>
            <input type="text" name="vehicle_model" value="{{ old('vehicle_model', $profile->vehicle_model ?? '') }}"
                   class="form-control @error('vehicle_model') is-invalid @enderror">
            @error('vehicle_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Màu xe</label>
            <input type="text" name="vehicle_color" value="{{ old('vehicle_color', $profile->vehicle_color ?? '') }}"
                   class="form-control @error('vehicle_color') is-invalid @enderror">
            @error('vehicle_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @endif
    </div>
</div>
@endif

@if(in_array('bank', $sections, true))
@php
    $bankOptions = ['Vietcombank', 'Techcombank', 'BIDV', 'VietinBank', 'MB Bank', 'ACB', 'Sacombank', 'TPBank', 'VPBank'];
    $currentBank = old('bank_name', $profile->bank_name ?? '');
@endphp
<div class="register-section" data-field-section="bank">
    <div class="register-section-title"><span class="section-icon">🏦</span> Thông tin ngân hàng</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Tên ngân hàng {!! $star('bank_name') !!}</label>
            <select name="bank_name" class="form-select register-bank-select @error('bank_name') is-invalid @enderror" {{ $req('bank_name') }}>
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
            <label class="form-label">Số tài khoản {!! $star('bank_account') !!}</label>
            <input type="text" name="bank_account" value="{{ old('bank_account', $profile->bank_account ?? '') }}"
                   class="form-control @error('bank_account') is-invalid @enderror"
                   inputmode="numeric" {{ $req('bank_account') }}>
            @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
@endif
