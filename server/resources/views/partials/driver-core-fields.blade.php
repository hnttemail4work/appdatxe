{{-- Trường thông tin tài xế — wizard đăng ký --}}
@php
    use App\Support\BankOptions;
    use App\Support\DriverFieldRules;
    use App\Support\DriverVehicleOptions;

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
    $vehicleType = old('vehicle_type', $profile->vehicle_type ?? '');
    $bankOptions = BankOptions::names();
    $currentBank = old('bank_name', $profile->bank_name ?? '');
@endphp

@if(in_array('account', $sections, true) || in_array('contact', $sections, true))
<div class="register-section" data-field-section="account">
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label">Họ tên {!! $star('name') !!}</label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}"
                   class="form-control @error('name') is-invalid @enderror" {{ $req('name') }}
                   autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                   placeholder="Nguyễn Văn A">
            <div class="invalid-feedback" data-client-feedback="name">@error('name'){{ $message }}@enderror</div>
        </div>
        <div class="col-12">
            <label class="form-label">SĐT {!! $star('phone') !!}</label>
            <input type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                   class="form-control @error('phone') is-invalid @enderror" {{ $req('phone') }}
                   inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                   placeholder="09xxxxxxxx">
            <div class="invalid-feedback" data-client-feedback="phone">@error('phone'){{ $message }}@enderror</div>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">Mật khẩu {!! $star('password') !!}</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" {{ $req('password') }}
                   minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
                   title="Tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và số"
                   autocomplete="new-password" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true"
                   placeholder="••••••••">
            <div class="invalid-feedback" data-client-feedback="password">@error('password'){{ $message }}@enderror</div>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">Nhập lại MK {!! $star('password_confirmation') !!}</label>
            <input type="password" name="password_confirmation"
                   class="form-control @error('password_confirmation') is-invalid @enderror" {{ $req('password_confirmation') }}
                   minlength="8" autocomplete="new-password" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true"
                   placeholder="••••••••">
            <div class="invalid-feedback" data-client-feedback="password_confirmation">@error('password_confirmation'){{ $message }}@enderror</div>
        </div>
        <div class="col-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ $profileEmail }}"
                   class="form-control @error('email') is-invalid @enderror"
                   autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                   data-wizard-skip-validity="1"
                   placeholder="email@example.com">
            <div class="invalid-feedback" data-client-feedback="email">@error('email'){{ $message }}@enderror</div>
        </div>
    </div>
</div>
@endif

@if(in_array('vehicle', $sections, true))
<div class="register-section" data-field-section="vehicle">
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label">Biển số {!! $star('vehicle_license_plate') !!}</label>
            <input type="text" name="vehicle_license_plate"
                   value="{{ old('vehicle_license_plate', $profile->vehicle_license_plate ?? '') }}"
                   class="form-control @error('vehicle_license_plate') is-invalid @enderror" {{ $req('vehicle_license_plate') }}
                   data-plate-format autocomplete="off" placeholder="51A-12345">
            <div class="invalid-feedback" data-client-feedback="vehicle_license_plate">@error('vehicle_license_plate'){{ $message }}@enderror</div>
        </div>
        <div class="col-12">
            <label class="form-label">Loại xe {!! $star('vehicle_type') !!}</label>
            <select name="vehicle_type" class="form-select @error('vehicle_type') is-invalid @enderror" {{ $req('vehicle_type') }}>
                <option value="">-- Chọn --</option>
                @foreach(DriverVehicleOptions::labels() as $val => $lbl)
                    <option value="{{ $val }}" {{ $vehicleType === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
                @if($vehicleType && ! array_key_exists($vehicleType, DriverVehicleOptions::labels()))
                    <option value="{{ $vehicleType }}" selected>{{ DriverVehicleOptions::label($vehicleType) }}</option>
                @endif
            </select>
            <div class="invalid-feedback" data-client-feedback="vehicle_type">@error('vehicle_type'){{ $message }}@enderror</div>
        </div>
        @if(! $compact)
        <div class="col-12 col-sm-6">
            <label class="form-label">Hãng xe</label>
            <input type="text" name="vehicle_brand" value="{{ old('vehicle_brand', $profile->vehicle_brand ?? '') }}"
                   class="form-control @error('vehicle_brand') is-invalid @enderror">
            <div class="invalid-feedback" data-client-feedback="vehicle_brand">@error('vehicle_brand'){{ $message }}@enderror</div>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">Model</label>
            <input type="text" name="vehicle_model" value="{{ old('vehicle_model', $profile->vehicle_model ?? '') }}"
                   class="form-control @error('vehicle_model') is-invalid @enderror">
            <div class="invalid-feedback" data-client-feedback="vehicle_model">@error('vehicle_model'){{ $message }}@enderror</div>
        </div>
        <div class="col-12">
            <label class="form-label">Màu</label>
            <input type="text" name="vehicle_color" value="{{ old('vehicle_color', $profile->vehicle_color ?? '') }}"
                   class="form-control @error('vehicle_color') is-invalid @enderror">
            <div class="invalid-feedback" data-client-feedback="vehicle_color">@error('vehicle_color'){{ $message }}@enderror</div>
        </div>
        @endif
    </div>
</div>
@endif

@if(in_array('bank', $sections, true))
<div class="register-section" data-field-section="bank">
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label">Ngân hàng {!! $star('bank_name') !!}</label>
            <select name="bank_name" class="form-select register-bank-select @error('bank_name') is-invalid @enderror" {{ $req('bank_name') }}>
                <option value="">-- Chọn --</option>
                @foreach($bankOptions as $bank)
                    <option value="{{ $bank }}" {{ $currentBank === $bank ? 'selected' : '' }}>{{ $bank }}</option>
                @endforeach
                @if($currentBank && ! in_array($currentBank, $bankOptions, true))
                    <option value="{{ $currentBank }}" selected>{{ $currentBank }}</option>
                @endif
            </select>
            <div class="invalid-feedback" data-client-feedback="bank_name">@error('bank_name'){{ $message }}@enderror</div>
        </div>
        <div class="col-12">
            <label class="form-label">Số TK {!! $star('bank_account') !!}</label>
            <input type="text" name="bank_account" value="{{ old('bank_account', $profile->bank_account ?? '') }}"
                   class="form-control @error('bank_account') is-invalid @enderror"
                   inputmode="numeric" {{ $req('bank_account') }} autocomplete="off"
                   placeholder="Số tài khoản">
            <div class="invalid-feedback" data-client-feedback="bank_account">@error('bank_account'){{ $message }}@enderror</div>
        </div>
    </div>
</div>
@endif
