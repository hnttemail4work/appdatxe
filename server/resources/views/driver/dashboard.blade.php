@extends('layouts.app')

@section('content')
<div class="row g-4">

    {{-- Cột trái: thông tin + trạng thái --}}
    <div class="col-lg-4">

        {{-- Thông tin cá nhân --}}
        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex gap-3 align-items-start mb-3">
                {{-- Avatar chân dung --}}
                <div class="flex-shrink-0">
                    @if($profile?->photo_portrait)
                        <img src="{{ $profile->photoUrl('photo_portrait') }}" alt="Chân dung"
                             class="rounded-circle object-fit-cover border"
                             style="width:60px;height:60px;">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center border"
                             style="width:60px;height:60px;font-size:1.4rem;font-weight:700;">
                            {{ mb_substr($user->name, 0, 1) }}
                        </div>
                    @endif
                </div>
                <div>
                    <h5 class="mb-0 fw-bold">{{ $user->name }}</h5>
                    <span class="badge bg-primary small">Tài xế</span>
                    @if($profile)
                        <span class="badge bg-{{ match($profile->status) { 'active'=>'success','suspended'=>'danger',default=>'secondary' } }} small ms-1">
                            {{ match($profile->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' } }}
                        </span>
                    @endif
                </div>
            </div>

            <h6 class="card-title-bar mb-3">Thông tin cá nhân</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Email</span>
                    <span class="small">{{ $user->email }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Điện thoại</span>
                    <span class="small">{{ $user->phone ?? '—' }}</span>
                </div>
                @if($user->address)
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Địa chỉ</span>
                    <span class="small text-end">{{ $user->address }}</span>
                </div>
                @endif
                @if($user->id_number)
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">CCCD/CMND</span>
                    <code class="small">{{ $user->id_number }}</code>
                </div>
                @endif
            </div>

            @if($profile)
            <hr class="my-3">
            <h6 class="text-muted mb-2 small fw-semibold">BẰNG LÁI XE</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Số bằng</span>
                    <code class="small">{{ $profile->license_number }}</code>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Hạng bằng</span>
                    <span class="badge bg-primary">Hạng {{ $profile->license_class }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Hết hạn</span>
                    <span class="small">
                        {{ $profile->license_expiry->format('d/m/Y') }}
                        @if($profile->license_expiry->isPast())
                            <span class="badge bg-danger ms-1">Hết hạn</span>
                        @elseif($profile->license_expiry->diffInDays(now()) < 60)
                            <span class="badge bg-warning text-dark ms-1">Sắp HH</span>
                        @endif
                    </span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Kinh nghiệm</span>
                    <span class="small">{{ $profile->experience_years }} năm</span>
                </div>
                @if($profile->operator)
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Quản lý bởi</span>
                    <span class="small fw-semibold">{{ $profile->operator->name }}</span>
                </div>
                @endif
                @if($profile->notes)
                <div>
                    <span class="text-muted small d-block">Ghi chú</span>
                    <div class="small text-muted">{{ $profile->notes }}</div>
                </div>
                @endif
            </div>

            {{-- Ảnh hồ sơ --}}
            <hr class="my-3">
            <h6 class="text-muted mb-2 small fw-semibold">HỒ SƠ & GIẤY TỜ</h6>

            {{-- CCCD 2 mặt --}}
            <div class="mb-2">
                <div class="small text-muted mb-1">Căn cước công dân</div>
                <div class="d-flex gap-2">
                    @foreach(['photo_id_card' => 'Mặt trước', 'photo_id_card_back' => 'Mặt sau'] as $col => $lbl)
                        @if($profile->{$col})
                            <div>
                                <a href="{{ $profile->photoUrl($col) }}" target="_blank">
                                    <img src="{{ $profile->photoUrl($col) }}" alt="{{ $lbl }}"
                                         class="rounded border object-fit-cover" style="width:88px;height:56px;"
                                         title="{{ $lbl }}">
                                </a>
                                <div class="text-muted" style="font-size:.65rem;text-align:center;">{{ $lbl }}</div>
                            </div>
                        @else
                            <div class="rounded border bg-light d-flex flex-column align-items-center justify-content-center text-muted"
                                 style="width:88px;height:56px;font-size:.65rem;">{{ $lbl }}</div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Ảnh xe --}}
            @php $vehicleUrls = $profile->vehiclePhotoUrls(); @endphp
            @if(count($vehicleUrls))
            <div>
                <div class="small text-muted mb-1">Ảnh xe ({{ count($vehicleUrls) }})</div>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach($vehicleUrls as $i => $url)
                        <a href="{{ $url }}" target="_blank">
                            <img src="{{ $url }}" alt="Xe {{ $i+1 }}"
                                 class="rounded border object-fit-cover" style="width:72px;height:48px;">
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            @else
            <div class="alert alert-warning mt-3 mb-0 py-2 small">
                Chưa có hồ sơ tài xế. Liên hệ quản lý để được cập nhật.
            </div>
            @endif
        </div>

        {{-- Trạng thái hoạt động --}}
        @if($profile)
        <div class="card shadow-sm p-4">
            <h5 class="card-title-bar mb-3">Trạng thái hoạt động</h5>
            @php
                $avail = $profile->availability_status ?? 'off_duty';
                $availConfig = [
                    'available' => ['label' => 'Sẵn sàng nhận chuyến', 'color' => 'success',  'icon' => '🟢'],
                    'on_trip'   => ['label' => 'Đang chạy chuyến',     'color' => 'primary',   'icon' => '🔵'],
                    'off_duty'  => ['label' => 'Nghỉ / Không nhận',    'color' => 'secondary', 'icon' => '⚫'],
                ];
            @endphp
            <div class="mb-3 text-center">
                <span class="fs-4">{{ $availConfig[$avail]['icon'] }}</span>
                <div class="mt-1">
                    <span class="badge bg-{{ $availConfig[$avail]['color'] }} fs-6 px-3 py-2">
                        {{ $availConfig[$avail]['label'] }}
                    </span>
                </div>
            </div>
            <form method="POST" action="{{ route('driver.availability.update') }}">
                @csrf @method('PATCH')
                <div class="d-flex flex-column gap-2">
                    @foreach($availConfig as $val => $cfg)
                        <button type="submit" name="availability_status" value="{{ $val }}"
                            class="btn btn-{{ $avail === $val ? $cfg['color'] : 'outline-'.$cfg['color'] }} text-start">
                            {{ $cfg['icon'] }} {{ $cfg['label'] }}
                        </button>
                    @endforeach
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- Cột phải: lịch chạy --}}
    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <h4 class="card-title-bar mb-3">Lịch chạy của tôi</h4>
            @if($schedules->isEmpty())
                <p class="text-muted">Chưa có lịch chạy nào được phân công.</p>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($schedules as $s)
                    @php $isToday = $s->departure_time->isToday(); @endphp
                    <div class="border rounded-3 p-3 {{ $isToday ? 'border-primary bg-light' : '' }}">
                        <div class="row align-items-center g-2">
                            <div class="col-md-4">
                                @if($isToday)
                                    <span class="badge bg-primary mb-1">Hôm nay</span><br>
                                @endif
                                <strong>{{ $s->route->departure }} → {{ $s->route->destination }}</strong><br>
                                <span class="text-muted small">{{ $s->departure_time->format('H:i · d/m/Y') }}</span>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Xe</span>
                                {{ ucfirst($s->vehicle->type) }}<br>
                                <small class="text-muted">{{ $s->vehicle->license_plate }} · {{ $s->vehicle->capacity }} ghế</small>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Đã đặt</span>
                                <strong>{{ $s->vehicle->capacity - $s->available_seats }}</strong>
                                <span class="text-muted">/ {{ $s->vehicle->capacity }} ghế</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-{{ match($s->status) {
                                    'running'   => 'primary',
                                    'completed' => 'secondary',
                                    'cancelled' => 'danger',
                                    default     => 'warning text-dark'
                                } }}">
                                    {{ match($s->status) {
                                        'scheduled' => 'Đã lên lịch',
                                        'running'   => 'Đang chạy',
                                        'completed' => 'Hoàn thành',
                                        'cancelled' => 'Đã hủy',
                                        default     => ucfirst($s->status)
                                    } }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
