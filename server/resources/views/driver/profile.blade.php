@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Hồ sơ tài xế</h3>
            <p class="text-muted mb-0">Điền đầy đủ thông tin cá nhân, bằng lái và ảnh giấy tờ.</p>
        </div>
        <a href="{{ route('driver.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Lịch chạy</a>
    </div>

    @if(!$profile)
    <div class="col-12">
        <div class="alert alert-warning">
            Chưa có hồ sơ tài xế. Vui lòng liên hệ quản lý để được tạo tài khoản hồ sơ.
        </div>
    </div>
    @else

    {{-- Thông tin cá nhân + bằng lái --}}
    <div class="col-lg-7">
        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex gap-3 align-items-center mb-4">
                @if($profile->photo_portrait)
                    <img src="{{ $profile->photoUrl('photo_portrait') }}" alt="Chân dung"
                         class="rounded-circle object-fit-cover border" style="width:64px;height:64px;">
                @else
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                         style="width:64px;height:64px;font-size:1.4rem;font-weight:700;">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                @endif
                <div>
                    <h5 class="mb-0">{{ $user->name }}</h5>
                    <span class="text-muted small">{{ $user->email }}</span>
                    @if($profile->operator)
                        <br><span class="badge bg-light text-dark border mt-1">Đơn vị: {{ $profile->operator->name }}</span>
                    @endif
                    <br><span class="badge bg-primary mt-1">Vai trò: Tài xế</span>
                </div>
            </div>

            <form method="POST" action="{{ route('driver.profile.update') }}">
                @csrf @method('PATCH')

                <h6 class="text-muted border-bottom pb-2 mb-3">Thông tin liên hệ</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                               class="form-control @error('phone') is-invalid @enderror" required>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CCCD / CMND</label>
                        <input type="text" name="id_number" value="{{ old('id_number', $user->id_number) }}"
                               class="form-control @error('id_number') is-invalid @enderror" placeholder="012345678901">
                        @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Địa chỉ</label>
                        <input type="text" name="address" value="{{ old('address', $user->address) }}"
                               class="form-control @error('address') is-invalid @enderror">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-3">Bằng lái xe</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Số bằng lái</label>
                        <input type="text" name="license_number" value="{{ old('license_number', $profile->license_number) }}"
                               class="form-control @error('license_number') is-invalid @enderror">
                        @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hạng bằng</label>
                        <select name="license_class" class="form-select @error('license_class') is-invalid @enderror">
                            @foreach(['B1','B2','C','D','E','F'] as $cls)
                                <option value="{{ $cls }}" {{ old('license_class', $profile->license_class) === $cls ? 'selected' : '' }}>{{ $cls }}</option>
                            @endforeach
                        </select>
                        @error('license_class')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày hết hạn bằng</label>
                        <input type="date" name="license_expiry"
                               value="{{ old('license_expiry', $profile->license_expiry->format('Y-m-d')) }}"
                               class="form-control @error('license_expiry') is-invalid @enderror">
                        @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số năm kinh nghiệm</label>
                        <input type="number" name="experience_years" min="0" max="50"
                               value="{{ old('experience_years', $profile->experience_years) }}"
                               class="form-control @error('experience_years') is-invalid @enderror">
                        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-3">Thông tin ngân hàng</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tên ngân hàng</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}"
                               class="form-control @error('bank_name') is-invalid @enderror"
                               placeholder="VD: Vietcombank">
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số tài khoản ngân hàng</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $profile->bank_account) }}"
                               class="form-control @error('bank_account') is-invalid @enderror"
                               placeholder="VD: 0123456789">
                        @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-3">Ghi chú</h6>
                <div class="mb-3">
                    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                              placeholder="Thông tin bổ sung (nếu có)...">{{ old('notes', $profile->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary">Lưu hồ sơ</button>
            </form>
        </div>

        {{-- Upload ảnh --}}
        <div class="card shadow-sm p-4">
            <h5 class="mb-1">Ảnh hồ sơ & giấy tờ</h5>
            <p class="text-muted small mb-3">Upload ảnh chân dung, CCCD hai mặt và ảnh xe. Chọn ảnh cần thay, bấm Lưu một lần.</p>
            @error('photos')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror
            @include('partials.driver-photo-upload-form', [
                'action'      => route('driver.photos.upload'),
                'submitLabel' => 'Lưu ảnh',
            ])
        </div>
    </div>

    {{-- Xem ảnh hiện tại --}}
    <div class="col-lg-5">
        <div class="card shadow-sm p-4 mb-4">
            <h6 class="text-muted mb-3">Ảnh đã upload</h6>

            <div class="mb-3">
                <small class="text-muted d-block mb-1">Chân dung</small>
                @if($profile->photo_portrait)
                    <a href="{{ $profile->photoUrl('photo_portrait') }}" target="_blank">
                        <img src="{{ $profile->photoUrl('photo_portrait') }}" alt="Chân dung"
                             class="rounded border object-fit-cover" style="width:100px;height:120px;">
                    </a>
                @else
                    <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                         style="width:100px;height:120px;font-size:.75rem;">Chưa có</div>
                @endif
            </div>

            <div class="mb-3">
                <small class="text-muted d-block mb-1">Căn cước công dân</small>
                <div class="d-flex gap-2">
                    @foreach(['photo_id_card' => 'Mặt trước', 'photo_id_card_back' => 'Mặt sau'] as $col => $lbl)
                        @if($profile->{$col})
                            <div class="text-center">
                                <a href="{{ $profile->photoUrl($col) }}" target="_blank">
                                    <img src="{{ $profile->photoUrl($col) }}" alt="{{ $lbl }}"
                                         class="rounded border object-fit-cover" style="width:100px;height:64px;">
                                </a>
                                <div class="text-muted" style="font-size:.65rem;">{{ $lbl }}</div>
                            </div>
                        @else
                            <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                                 style="width:100px;height:64px;font-size:.65rem;">{{ $lbl }}</div>
                        @endif
                    @endforeach
                </div>
            </div>

            @php $vehicleUrls = $profile->vehiclePhotoUrls(); @endphp
            <div>
                <small class="text-muted d-block mb-1">Ảnh xe ({{ count($vehicleUrls) }})</small>
                <div class="d-flex gap-2 flex-wrap">
                    @forelse($vehicleUrls as $i => $url)
                        <a href="{{ $url }}" target="_blank">
                            <img src="{{ $url }}" alt="Xe {{ $i+1 }}"
                                 class="rounded border object-fit-cover" style="width:80px;height:56px;">
                        </a>
                    @empty
                        <span class="text-muted small">Chưa có ảnh xe.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <h6 class="text-muted mb-3">Trạng thái tài khoản</h6>
            <dl class="small mb-0">
                <dt class="text-muted">Trạng thái</dt>
                <dd>
                    <span class="badge bg-{{ match($profile->status) { 'active'=>'success','suspended'=>'danger',default=>'secondary' } }}">
                        {{ match($profile->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' } }}
                    </span>
                </dd>
                <dt class="text-muted mt-2">Ngân hàng</dt>
                <dd>{{ $profile->bank_name ?: '—' }} · {{ $profile->bank_account ?: '—' }}</dd>
                <dt class="text-muted mt-2">Trạng thái làm việc</dt>
                <dd>
                    @php $avail = $profile->availability_status ?? 'off_duty'; @endphp
                    <span class="badge bg-{{ match($avail) { 'available'=>'success','on_trip'=>'primary',default=>'secondary' } }}">
                        {{ match($avail) { 'available'=>'Sẵn sàng','on_trip'=>'Đang chạy',default=>'Nghỉ' } }}
                    </span>
                    <br><a href="{{ route('driver.dashboard') }}" class="small">Đổi trạng thái tại Lịch chạy →</a>
                </dd>
            </dl>
        </div>
    </div>
    @endif
</div>
@endsection
