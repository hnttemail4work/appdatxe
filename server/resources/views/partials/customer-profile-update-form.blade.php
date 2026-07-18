@php
    $pendingChange = $pendingChange ?? null;
@endphp
<div class="customer-account-card mt-3">
    <h2 class="customer-account-card__title">Cập nhật thông tin</h2>
    <p class="small text-muted mb-3">Thay đổi sẽ được gửi cho admin duyệt trước khi áp dụng.</p>

    @if($pendingChange)
        <div class="alert alert-warning py-2 small mb-3" role="status">
            Đang có yêu cầu cập nhật chờ duyệt (#{{ $pendingChange->id }}).
            Gửi lại sẽ ghi đè yêu cầu cũ.
        </div>
    @endif

    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="customer-profile-update-form">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="customer-profile-name">Họ và tên</label>
                <input type="text" name="name" id="customer-profile-name"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $user->preferredDisplayName() === $user->phone ? '' : $user->name) }}"
                       autocomplete="name" placeholder="Nguyễn Văn A">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-profile-email">Gmail</label>
                <input type="email" name="email" id="customer-profile-email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $user->emailForForm()) }}"
                       autocomplete="email" placeholder="email@gmail.com">
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Giới tính</label>
                @php $gender = old('gender', $user->gender); @endphp
                <div class="booking-chip-group booking-chip-group--inline">
                    <label class="form-check booking-chip">
                        <input type="radio" name="gender" value="male" class="form-check-input" @checked($gender === 'male')> Nam
                    </label>
                    <label class="form-check booking-chip">
                        <input type="radio" name="gender" value="female" class="form-check-input" @checked($gender === 'female')> Nữ
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-profile-dob">Ngày sinh</label>
                <input type="date" name="date_of_birth" id="customer-profile-dob"
                       class="form-control @error('date_of_birth') is-invalid @enderror"
                       value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"
                       max="{{ now()->subDay()->toDateString() }}">
                @error('date_of_birth')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-profile-id-number">Số CCCD</label>
                <input type="text" name="id_number" id="customer-profile-id-number"
                       class="form-control @error('id_number') is-invalid @enderror"
                       value="{{ old('id_number', $user->id_number) }}"
                       maxlength="20" placeholder="001234567890">
                @error('id_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-profile-address">Địa chỉ</label>
                <input type="text" name="address" id="customer-profile-address"
                       class="form-control @error('address') is-invalid @enderror"
                       value="{{ old('address', $user->address) }}"
                       maxlength="255" placeholder="Số nhà, đường, khu vực">
                @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <p class="form-label mb-2">Ảnh CCCD (tuỳ chọn khi cập nhật)</p>
                @include('partials.customer-docs-upload-register', [
                    'idCardRequired' => false,
                    'inputIdPrefix' => 'cust-update',
                ])
            </div>
        </div>
        @error('profile')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
        <button type="submit" class="btn btn-primary w-100 mt-3">Gửi yêu cầu duyệt</button>
    </form>
</div>
