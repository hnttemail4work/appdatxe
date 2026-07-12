@php
    $user = $driver->user;
    $portraitUrl = $driver->photoUrl('photo_portrait');
@endphp
<div class="driver-edit-header">
    <div class="driver-edit-identity">
        @if($portraitUrl)
            <a href="{{ $portraitUrl }}" target="_blank" rel="noopener">
                <img src="{{ $portraitUrl }}" alt=""
                     class="driver-edit-avatar rounded-circle object-fit-cover border">
            </a>
        @else
            <div class="driver-edit-avatar driver-edit-avatar-fallback rounded-circle">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif
        <div>
            <h2 class="driver-edit-title">{{ $user->name }}</h2>
<span class="status-pill status-pill--{{ $driver->displayStatusColor() }}">{{ $driver->displayStatusLabel() }}</span>
            @if($driver->driver_code)
                <span class="driver-meta-code ms-1">{{ $driver->driver_code }}</span>
            @endif
        </div>
    </div>

    @if($driver->isPendingApproval())
        <div class="driver-edit-actions">
            @include('partials.driver-approval-actions', ['driver' => $driver, 'compact' => true])
        </div>
    @elseif($driver->isRejected())
        <div class="driver-edit-actions">
            <span class="text-danger small fw-semibold d-block">Hồ sơ đã từ chối — tài xế không đăng nhập được.</span>
        </div>
    @elseif($driver->isMissedTripLocked())
        <div class="driver-edit-actions">
            <span class="text-danger small fw-semibold d-block mb-2">Tạm khóa — không nhận chuyến.</span>
            <form method="POST" action="{{ route('admin.drivers.unlock', $driver) }}">
                @csrf
                <button class="btn btn-sm btn-outline-primary">Mở khóa</button>
            </form>
        </div>
    @elseif($driver->hasCancelRate())
        <div class="driver-edit-actions">
            <span class="text-warning small fw-semibold d-block mb-1">Tỷ lệ hủy cuốc: {{ $driver->cancelRateLabel() }}</span>
            <form method="POST"
                  action="{{ route('admin.drivers.resetCancelRate', $driver) }}"
                  data-confirm="Đặt lại tỷ lệ hủy cuốc về 0%?"
                  data-confirm-title="Reset tỷ lệ hủy cuốc"
                  data-confirm-ok="Đặt về 0%"
                  data-confirm-variant="warning">
                @csrf
                <button class="btn btn-sm btn-outline-warning">Đặt về 0%</button>
            </form>
        </div>
    @endif
</div>
