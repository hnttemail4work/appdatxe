@extends('layouts.app')

@section('content')
@php
    $vehicleUrls = $driver->vehiclePhotoUrls();
@endphp
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Sửa tài xế: {{ $driver->user->name }}</h3>
            <p class="text-muted mb-0">Cập nhật thông tin, trạng thái và ảnh hồ sơ.</p>
        </div>
        <a href="{{ route('operator.drivers') }}" class="btn btn-outline-secondary btn-sm">← Danh sách tài xế</a>
    </div>

    {{-- Form thông tin --}}
    <div class="col-lg-8">
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="mb-3">Thông tin tài xế</h5>
            <form method="POST" action="{{ route('operator.drivers.update', $driver) }}">
                @csrf @method('PATCH')
                @include('partials.driver-form-fields', [
                    'mode'      => 'edit',
                    'driver'    => $driver,
                    'operators' => $operators,
                ])
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary">Lưu thông tin</button>
                    <a href="{{ route('operator.drivers') }}" class="btn btn-outline-secondary">Huỷ</a>
                </div>
            </form>
        </div>

        {{-- Ảnh hiện có --}}
        <div class="card shadow-sm p-4 mb-4">
            <h5 class="mb-3">Ảnh hồ sơ hiện tại</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">Chân dung</small>
                    @if($driver->photo_portrait)
                        <a href="{{ $driver->photoUrl('photo_portrait') }}" target="_blank">
                            <img src="{{ $driver->photoUrl('photo_portrait') }}" alt="Chân dung"
                                 class="rounded border object-fit-cover" style="width:100%;max-width:120px;height:140px;">
                        </a>
                    @else
                        <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                             style="width:120px;height:140px;font-size:.75rem;">Chưa có</div>
                    @endif
                </div>
                @foreach(['photo_id_card' => 'CCCD mặt trước', 'photo_id_card_back' => 'CCCD mặt sau'] as $col => $lbl)
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">{{ $lbl }}</small>
                    @if($driver->{$col})
                        <a href="{{ $driver->photoUrl($col) }}" target="_blank">
                            <img src="{{ $driver->photoUrl($col) }}" alt="{{ $lbl }}"
                                 class="rounded border object-fit-cover" style="width:100%;max-width:160px;height:100px;">
                        </a>
                    @else
                        <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                             style="width:160px;height:100px;font-size:.75rem;">Chưa có</div>
                    @endif
                </div>
                @endforeach
                <div class="col-12">
                    <small class="text-muted d-block mb-1">Ảnh xe ({{ count($vehicleUrls) }})</small>
                    <div class="d-flex gap-2 flex-wrap">
                        @forelse($vehicleUrls as $idx => $url)
                            <div class="position-relative">
                                <a href="{{ $url }}" target="_blank">
                                    <img src="{{ $url }}" alt="Xe {{ $idx+1 }}"
                                         class="rounded border object-fit-cover" style="width:90px;height:64px;">
                                </a>
                                <form method="POST" action="{{ route('operator.drivers.photos', $driver) }}"
                                      class="d-inline" style="position:absolute;top:-6px;right:-6px;">
                                    @csrf
                                    <input type="hidden" name="delete_vehicle_idx" value="{{ $idx }}">
                                    <button type="submit" class="btn btn-danger btn-sm p-0 lh-1"
                                            style="width:18px;height:18px;font-size:.65rem;"
                                            onclick="return confirm('Xóa ảnh này?')" title="Xóa">×</button>
                                </form>
                            </div>
                        @empty
                            <span class="text-muted small">Chưa có ảnh xe.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Upload ảnh --}}
        <div class="card shadow-sm p-4">
            <h5 class="mb-3">Upload / cập nhật ảnh</h5>
            @error('photos')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror
            @include('partials.driver-photo-upload-form', [
                'action'      => route('operator.drivers.photos', $driver),
                'title'       => null,
                'submitLabel' => 'Lưu ảnh',
            ])
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        <div class="card shadow-sm p-4">
            <h6 class="text-muted mb-3">Tóm tắt</h6>
            <dl class="small mb-0">
                <dt class="text-muted">Email</dt>
                <dd>{{ $driver->user->email }}</dd>
                <dt class="text-muted">Quản lý bởi</dt>
                <dd>{{ $driver->operator?->name ?? '—' }}</dd>
                <dt class="text-muted">Ngày tạo hồ sơ</dt>
                <dd>{{ $driver->created_at->format('d/m/Y H:i') }}</dd>
            </dl>
            @if($driver->status !== 'inactive')
            <hr>
            <form method="POST" action="{{ route('operator.drivers.destroy', $driver) }}"
                  onsubmit="return confirm('Vô hiệu hoá tài xế này? Tài xế sẽ không đăng nhập được.')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger w-100">Vô hiệu hoá tài xế</button>
            </form>
            @else
            <div class="alert alert-secondary small mt-3 mb-0 py-2">Tài xế đang ở trạng thái không hoạt động.</div>
            @endif
        </div>
    </div>
</div>
@endsection
