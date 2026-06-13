@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0 card-title-bar">Quản lý tài xế</h3>
            <p class="text-muted mb-0">Thêm và cập nhật thông tin tài xế.</p>
        </div>
        <a href="{{ route('operator.dashboard') }}" class="btn btn-outline-primary btn-sm">← Về Dashboard</a>
    </div>

    @if(auth()->user()->role === 'operator')
    <div class="col-lg-5">
        <div class="card shadow-sm p-4">
            <h5>Thêm tài xế mới</h5>
            <form method="POST" action="{{ route('operator.drivers.store') }}" class="mt-3">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" required>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required placeholder="Tối thiểu 8 ký tự">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CCCD/CMND</label>
                        <input type="text" name="id_number" value="{{ old('id_number') }}" class="form-control" placeholder="012345678901">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Địa chỉ</label>
                        <input type="text" name="address" value="{{ old('address') }}" class="form-control">
                    </div>
                    <div class="col-12"><hr class="my-1"><strong class="small text-muted">THÔNG TIN BẰNG LÁI</strong></div>
                    <div class="col-md-6">
                        <label class="form-label">Số bằng lái <span class="text-danger">*</span></label>
                        <input type="text" name="license_number" value="{{ old('license_number') }}" class="form-control @error('license_number') is-invalid @enderror" required>
                        @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hạng bằng <span class="text-danger">*</span></label>
                        <select name="license_class" class="form-select" required>
                            @foreach(['B1','B2','C','D','E','F'] as $cls)
                                <option value="{{ $cls }}" {{ old('license_class','B2') === $cls ? 'selected' : '' }}>{{ $cls }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày hết hạn bằng <span class="text-danger">*</span></label>
                        <input type="date" name="license_expiry" value="{{ old('license_expiry') }}" class="form-control @error('license_expiry') is-invalid @enderror" required>
                        @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kinh nghiệm (năm)</label>
                        <input type="number" name="experience_years" value="{{ old('experience_years', 0) }}" min="0" max="50" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <button class="btn btn-primary w-100 mt-3">Thêm tài xế</button>
            </form>
        </div>
    </div>
    @endif

    <div class="{{ auth()->user()->role === 'operator' ? 'col-lg-7' : 'col-12' }}">
        <div class="card shadow-sm p-4">
            <h5>Danh sách tài xế ({{ $drivers->count() }})</h5>
            @if($drivers->isEmpty())
                <p class="text-muted mt-3">Chưa có tài xế nào.</p>
            @else
                <div class="d-flex flex-column gap-4 mt-3">
                @foreach($drivers as $d)
                @php
                    $availLabel = match($d->availability_status ?? 'off_duty') {
                        'available' => ['Sẵn sàng', 'success'],
                        'on_trip'   => ['Đang chạy', 'primary'],
                        default     => ['Nghỉ', 'secondary'],
                    };
                    $vehicleUrls = $d->vehiclePhotoUrls();
                @endphp
                <div class="border rounded-3 p-3">
                    {{-- Hàng 1: avatar + tên + badges --}}
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <div class="flex-shrink-0">
                            @if($d->photo_portrait)
                                <img src="{{ $d->photoUrl('photo_portrait') }}" alt="Chân dung"
                                     class="rounded-circle object-fit-cover" style="width:52px;height:52px;">
                            @else
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                     style="width:52px;height:52px;font-size:1.2rem;font-weight:700;">
                                    {{ mb_substr($d->user->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div>
                                <strong>{{ $d->user->name }}</strong>
                                <span class="badge bg-{{ match($d->status) { 'active'=>'primary','suspended'=>'danger',default=>'secondary' } }} ms-1">
                                    {{ match($d->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' } }}
                                </span>
                                <span class="badge bg-{{ $availLabel[1] }} ms-1">{{ $availLabel[0] }}</span>
                                @if($d->operator && auth()->user()->role === 'admin')
                                    <span class="badge bg-light text-dark border ms-1">{{ $d->operator->name }}</span>
                                @endif
                            </div>
                            <small class="text-muted">{{ $d->user->phone }} · {{ $d->user->email }}</small>
                            <div class="mt-1 small text-muted">
                                Hạng <strong>{{ $d->license_class }}</strong> ·
                                HH: {{ $d->license_expiry->format('d/m/Y') }}
                                @if($d->license_expiry->isPast())
                                    <span class="badge bg-danger">Hết hạn</span>
                                @elseif($d->license_expiry->diffInDays(now()) < 60)
                                    <span class="badge bg-warning text-dark">Sắp HH</span>
                                @endif
                                · {{ $d->experience_years }} năm KN
                            </div>
                        </div>
                    </div>

                    {{-- CCCD 2 mặt --}}
                    <div class="mb-2">
                        <small class="text-muted fw-semibold d-block mb-1">CCCD / Căn cước</small>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach(['photo_id_card' => 'Mặt trước', 'photo_id_card_back' => 'Mặt sau'] as $col => $lbl)
                                @if($d->{$col})
                                    <a href="{{ $d->photoUrl($col) }}" target="_blank">
                                        <img src="{{ $d->photoUrl($col) }}" alt="{{ $lbl }}"
                                             class="rounded border object-fit-cover" style="width:100px;height:64px;"
                                             title="{{ $lbl }}">
                                    </a>
                                @else
                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted"
                                         style="width:100px;height:64px;font-size:.72rem;">{{ $lbl }}</div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Chân dung --}}
                    <div class="mb-2">
                        <small class="text-muted fw-semibold d-block mb-1">Chân dung</small>
                        <div class="d-flex gap-2">
                            @if($d->photo_portrait)
                                <a href="{{ $d->photoUrl('photo_portrait') }}" target="_blank">
                                    <img src="{{ $d->photoUrl('photo_portrait') }}" alt="Chân dung"
                                         class="rounded border object-fit-cover" style="width:64px;height:80px;">
                                </a>
                            @else
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted"
                                     style="width:64px;height:80px;font-size:.72rem;">Chân dung</div>
                            @endif
                        </div>
                    </div>

                    {{-- Ảnh xe (nhiều) --}}
                    <div class="mb-3">
                        <small class="text-muted fw-semibold d-block mb-1">Ảnh xe ({{ count($vehicleUrls) }})</small>
                        <div class="d-flex gap-2 flex-wrap">
                            @forelse($vehicleUrls as $idx => $url)
                                <div class="position-relative">
                                    <a href="{{ $url }}" target="_blank">
                                        <img src="{{ $url }}" alt="Xe {{ $idx+1 }}"
                                             class="rounded border object-fit-cover" style="width:80px;height:56px;">
                                    </a>
                                    <form method="POST" action="{{ route('operator.drivers.photos', $d) }}"
                                          class="d-inline" style="position:absolute;top:-6px;right:-6px;">
                                        @csrf
                                        <input type="hidden" name="delete_vehicle_idx" value="{{ $idx }}">
                                        <button type="submit" class="btn btn-danger btn-sm p-0 lh-1"
                                                style="width:18px;height:18px;font-size:.65rem;"
                                                onclick="return confirm('Xóa ảnh này?')" title="Xóa">×</button>
                                    </form>
                                </div>
                            @empty
                                <div class="text-muted small">Chưa có ảnh xe.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Upload ảnh --}}
                    <form method="POST" action="{{ route('operator.drivers.photos', $d) }}"
                          enctype="multipart/form-data" class="border rounded p-2 bg-light mb-3">
                        @csrf
                        <small class="fw-semibold text-muted d-block mb-2">Upload / cập nhật ảnh</small>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">Chân dung</label>
                                <input type="file" name="photo_portrait" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt trước</label>
                                <input type="file" name="photo_id_card" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-0" style="font-size:.75rem;">CCCD mặt sau</label>
                                <input type="file" name="photo_id_card_back" accept="image/*" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-0" style="font-size:.75rem;">Thêm ảnh xe (chọn nhiều)</label>
                                <input type="file" name="photo_vehicles[]" accept="image/*" multiple class="form-control form-control-sm">
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary mt-2">Upload ảnh</button>
                    </form>

                    {{-- Cập nhật trạng thái --}}
                    <form method="POST" action="{{ route('operator.drivers.update', $d) }}"
                          class="d-flex gap-2 flex-wrap align-items-center mb-2">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm" style="width:130px">
                            @foreach(['active','inactive','suspended'] as $st)
                                <option value="{{ $st }}" {{ $d->status === $st ? 'selected' : '' }}>
                                    {{ match($st){ 'active'=>'Hoạt động','inactive'=>'Không HĐ','suspended'=>'Tạm ngưng' } }}
                                </option>
                            @endforeach
                        </select>
                        <select name="availability_status" class="form-select form-select-sm" style="width:130px">
                            <option value="available" {{ ($d->availability_status ?? '') === 'available' ? 'selected' : '' }}>Sẵn sàng</option>
                            <option value="on_trip"   {{ ($d->availability_status ?? '') === 'on_trip'   ? 'selected' : '' }}>Đang chạy</option>
                            <option value="off_duty"  {{ ($d->availability_status ?? 'off_duty') === 'off_duty'  ? 'selected' : '' }}>Nghỉ</option>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Lưu trạng thái</button>
                    </form>

                    {{-- Edit thông tin đầy đủ (collapse) --}}
                    <button class="btn btn-sm btn-link text-muted p-0 mb-1"
                            data-bs-toggle="collapse"
                            data-bs-target="#edit-info-{{ $d->id }}">Sửa thông tin chi tiết ▾</button>
                    <div class="collapse" id="edit-info-{{ $d->id }}">
                        <form method="POST" action="{{ route('operator.drivers.update', $d) }}"
                              class="border rounded p-3 bg-white mt-1">
                            @csrf @method('PATCH')
                            <div class="row g-2">
                                <div class="col-12"><small class="fw-semibold text-muted">Thông tin cá nhân</small></div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">Họ và tên</label>
                                    <input type="text" name="name" value="{{ $d->user->name }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">Số điện thoại</label>
                                    <input type="tel" name="phone" value="{{ $d->user->phone }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small">CCCD/CMND</label>
                                    <input type="text" name="id_number" value="{{ $d->user->id_number }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-0 small">Địa chỉ</label>
                                    <input type="text" name="address" value="{{ $d->user->address }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 mt-2"><small class="fw-semibold text-muted">Bằng lái xe</small></div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small">Số bằng lái</label>
                                    <input type="text" name="license_number" value="{{ $d->license_number }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0 small">Hạng bằng</label>
                                    <select name="license_class" class="form-select form-select-sm">
                                        @foreach(['B1','B2','C','D','E','F'] as $cls)
                                            <option value="{{ $cls }}" {{ $d->license_class === $cls ? 'selected' : '' }}>{{ $cls }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0 small">Hết hạn bằng</label>
                                    <input type="date" name="license_expiry" value="{{ $d->license_expiry->format('Y-m-d') }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0 small">Kinh nghiệm (năm)</label>
                                    <input type="number" name="experience_years" value="{{ $d->experience_years }}" min="0" max="50" class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-0 small">Ghi chú</label>
                                    <textarea name="notes" class="form-control form-control-sm" rows="2">{{ $d->notes }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-sm btn-primary">Lưu thông tin</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
