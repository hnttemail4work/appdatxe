@php
    $profile = $profile ?? [];
    $name = old('name', $profile['name'] ?? ($user->name ?? ''));
    $phone = $profile['phone'] ?? ($user->phone ?? '');
    $nameLooksLikePhone = $name !== '' && (
        $name === $phone
        || (bool) preg_match('/^[\d\s.+()-]+$/', (string) $name)
    );
    if ($nameLooksLikePhone) {
        $name = '';
    }
    $gender = old('gender', $profile['gender'] ?? '');
    $dob = old('date_of_birth', $profile['date_of_birth'] ?? '');
@endphp
<section class="customer-account-panel is-active" aria-label="Cập nhật thông tin">
    <div class="customer-account-subhead mb-3">
        <a href="{{ route('customer.account', ['tab' => 'account']) }}" class="customer-account-back" aria-label="Quay lại">←</a>
        <h2 class="customer-account-panel__title mb-0">Cập nhật thông tin</h2>
    </div>

    <div class="customer-account-card">
        <p class="small text-muted mb-3">Cập nhật họ tên, ngày sinh và giới tính. Thông tin sẽ dùng khi đặt xe.</p>

        <form method="POST" action="{{ route('customer.info.update') }}" class="customer-info-form">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="customer-info-name">Họ tên</label>
                <input type="text" name="name" id="customer-info-name"
                       class="form-control @error('name') is-invalid @enderror"
                       required maxlength="255" autocomplete="name"
                       value="{{ $name }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="customer-info-dob">Ngày sinh</label>
                <input type="date" name="date_of_birth" id="customer-info-dob"
                       class="form-control @error('date_of_birth') is-invalid @enderror"
                       required max="{{ now()->subYear()->format('Y-m-d') }}"
                       value="{{ $dob }}">
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <span class="form-label d-block">Giới tính</span>
                <div class="be-gender-toggle" role="group" aria-label="Giới tính">
                    <label class="be-gender-toggle__opt">
                        <input type="radio" name="gender" value="male" @checked($gender === 'male') required>
                        <span>Nam</span>
                    </label>
                    <label class="be-gender-toggle__opt">
                        <input type="radio" name="gender" value="female" @checked($gender === 'female')>
                        <span>Nữ</span>
                    </label>
                </div>
                @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold">Lưu thông tin</button>
        </form>
    </div>
</section>
