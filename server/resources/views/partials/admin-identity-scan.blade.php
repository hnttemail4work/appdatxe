{{--
  Panel nhập họ tên / ngày sinh / giới tính khi admin duyệt hồ sơ.
  Scan QR CCCD (từ ảnh trước hoặc chọn file) — lỗi thì nhập tay.
  @var string $prefix unique id prefix
  @var string|null $frontUrl ảnh CCCD mặt trước (scan)
  @var \App\Models\User|null $user prefill
--}}
@php
    $prefix = $prefix ?? 'idscan';
    $user = $user ?? null;
    $frontUrl = $frontUrl ?? null;
    $name = old('name', $user?->name && ! preg_match('/^[\d\s.+()-]+$/', (string) $user->name) ? $user->name : '');
    $dob = old('date_of_birth', $user?->date_of_birth?->format('Y-m-d') ?? '');
    $gender = old('gender', $user?->gender ?? 'male');
    if (! in_array($gender, ['male', 'female'], true)) {
        $gender = 'male';
    }
    $idNumber = old('id_number', $user?->id_number ?? '');
@endphp
<div class="admin-idscan" data-admin-idscan data-front-url="{{ $frontUrl }}">
    <div class="admin-idscan__head">
        <strong>Thông tin từ CCCD</strong>
        <div class="admin-idscan__actions">
            @if($frontUrl)
                <button type="button" class="btn btn-sm btn-outline-primary" data-idscan-from-front>
                    Scan ảnh CCCD
                </button>
            @endif
            <label class="btn btn-sm btn-outline-secondary mb-0">
                Scan file / camera
                <input type="file" accept="image/*" capture="environment" class="d-none" data-idscan-file>
            </label>
        </div>
    </div>
    <p class="admin-idscan__hint small text-muted mb-2" data-idscan-status>
        Bấm Scan để đọc QR trên CCCD. Lỗi thì nhập tay bên dưới.
    </p>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label" for="{{ $prefix }}-name">Họ và tên <span class="text-danger">*</span></label>
            <input type="text" name="name" id="{{ $prefix }}-name" required
                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                   value="{{ $name }}" autocomplete="off" data-idscan-name
                   placeholder="Nguyễn Văn A">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6">
            <label class="form-label" for="{{ $prefix }}-dob">Ngày sinh <span class="text-danger">*</span></label>
            <input type="date" name="date_of_birth" id="{{ $prefix }}-dob" required
                   class="form-control form-control-sm @error('date_of_birth') is-invalid @enderror"
                   value="{{ $dob }}" max="{{ now()->subYear()->format('Y-m-d') }}" data-idscan-dob>
            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text" data-idscan-age></div>
        </div>
        <div class="col-6">
            <label class="form-label d-block">Giới tính <span class="text-danger">*</span></label>
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
            <label class="form-label" for="{{ $prefix }}-id">Số CCCD</label>
            <input type="text" name="id_number" id="{{ $prefix }}-id"
                   class="form-control form-control-sm @error('id_number') is-invalid @enderror"
                   value="{{ $idNumber }}" inputmode="numeric" maxlength="20" data-idscan-id
                   placeholder="Để trống nếu scan không đọc được">
            @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    @if($frontUrl)
        <div class="admin-idscan__preview mt-2">
            <a href="{{ $frontUrl }}" target="_blank" rel="noopener" class="small">Xem ảnh CCCD mặt trước</a>
        </div>
    @endif
</div>
