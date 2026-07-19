{{--
  Panel nhập họ tên / ngày sinh (+ tuổi) / giới tính / số CCCD khi admin duyệt hồ sơ.
  @var string $prefix unique id prefix
  @var \App\Models\User|null $user prefill
  @var string|null $frontUrl
--}}
@php
    $prefix = $prefix ?? 'idscan';
    $user = $user ?? null;
    $frontUrl = $frontUrl ?? null;
    $name = old('name', $user?->name && ! preg_match('/^[\d\s.+()-]+$/', (string) $user->name) ? $user->name : '');
    $dob = old('date_of_birth', $user?->date_of_birth?->format('Y-m-d') ?? '');
    $gender = old('gender', $user?->gender ?? '');
    if (! in_array($gender, ['male', 'female'], true)) {
        $gender = '';
    }
    $idNumber = old('id_number', $user?->id_number ?? '');
    $address = old('address', $user?->address ?? '');
    $ageHint = $user?->age();
@endphp
<div class="admin-idscan"
     data-admin-idscan
     data-front-url="{{ $frontUrl }}">
    <div class="admin-idscan__head">
        <strong>Thông tin CCCD</strong>
    </div>
    <p class="admin-idscan__hint small text-muted mb-2" data-idscan-status>
        Xoay/cắt ảnh (nếu cần), nhập thông tin rồi bấm Duyệt — ảnh đã chỉnh lưu cùng lúc duyệt.
    </p>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label" for="{{ $prefix }}-name">
                Họ và tên <span class="admin-required" title="Bắt buộc">*</span>
            </label>
            <input type="text" name="name" id="{{ $prefix }}-name" required
                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                   value="{{ $name }}" autocomplete="name" data-idscan-name
                   placeholder="Nguyễn Văn A">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-7">
            <label class="form-label" for="{{ $prefix }}-dob">
                Ngày tháng năm sinh <span class="admin-required" title="Bắt buộc">*</span>
            </label>
            <input type="date" name="date_of_birth" id="{{ $prefix }}-dob" required
                   class="form-control form-control-sm @error('date_of_birth') is-invalid @enderror"
                   value="{{ $dob }}" max="{{ now()->subYear()->format('Y-m-d') }}" data-idscan-dob>
            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="form-label" for="{{ $prefix }}-age">
                Tuổi <span class="text-muted fw-normal">(tự tính)</span>
            </label>
            <input type="text" id="{{ $prefix }}-age" class="form-control form-control-sm" readonly
                   value="{{ $ageHint !== null ? $ageHint.' tuổi' : '—' }}"
                   data-idscan-age aria-live="polite" tabindex="-1">
        </div>
        <div class="col-12">
            <label class="form-label d-block">
                Giới tính <span class="admin-required" title="Bắt buộc">*</span>
            </label>
            <div class="btn-group w-100" role="group" aria-label="Giới tính">
                <input type="radio" class="btn-check" name="gender" id="{{ $prefix }}-male"
                       value="male" autocomplete="off" @checked($gender === 'male') required data-idscan-gender>
                <label class="btn btn-sm btn-outline-secondary" for="{{ $prefix }}-male">Nam</label>
                <input type="radio" class="btn-check" name="gender" id="{{ $prefix }}-female"
                       value="female" autocomplete="off" @checked($gender === 'female') data-idscan-gender>
                <label class="btn btn-sm btn-outline-secondary" for="{{ $prefix }}-female">Nữ</label>
            </div>
            @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="{{ $prefix }}-id">
                Số CCCD <span class="admin-required" title="Bắt buộc">*</span>
            </label>
            <input type="text" name="id_number" id="{{ $prefix }}-id" required
                   class="form-control form-control-sm @error('id_number') is-invalid @enderror"
                   value="{{ $idNumber }}" inputmode="numeric" maxlength="12" minlength="9"
                   pattern="\d{9,12}" data-idscan-id
                   placeholder="012345678901">
            @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="{{ $prefix }}-address">
                Địa chỉ <span class="admin-required" title="Bắt buộc">*</span>
            </label>
            <textarea name="address" id="{{ $prefix }}-address" required rows="2" maxlength="500"
                      class="form-control form-control-sm @error('address') is-invalid @enderror"
                      data-idscan-address
                      placeholder="Số nhà, đường, phường/xã, tỉnh/thành…">{{ $address }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
