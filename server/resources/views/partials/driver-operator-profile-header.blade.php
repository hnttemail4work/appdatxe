@php
    $user = $driver->user;
@endphp
<div class="driver-edit-header">
    <div class="driver-edit-identity">
        @if($driver->photo_portrait)
            <a href="{{ $driver->photoUrl('photo_portrait') }}" target="_blank" rel="noopener">
                <img src="{{ $driver->photoUrl('photo_portrait') }}" alt=""
                     class="driver-edit-avatar rounded-circle object-fit-cover border">
            </a>
        @else
            <div class="driver-edit-avatar driver-edit-avatar-fallback rounded-circle">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif
        <div>
            <h2 class="driver-edit-title">{{ $user->name }}</h2>
            <span class="badge bg-{{ $driver->displayStatusColor() }}">{{ $driver->displayStatusLabel() }}</span>
            @if($driver->driver_code)
                <code class="driver-edit-code ms-1">{{ $driver->driver_code }}</code>
            @endif
        </div>
    </div>

    @if($driver->isPendingApproval())
        <div class="driver-edit-actions">
            <p class="text-muted small mb-2 mb-md-0">Kiểm tra giấy tờ và thông tin bên dưới.</p>
            @include('partials.driver-approval-actions', ['driver' => $driver, 'compact' => true])
        </div>
    @elseif($driver->isRejected())
        <div class="driver-edit-actions">
            <span class="text-danger small fw-semibold">Hồ sơ đã từ chối — tài xế không đăng nhập được.</span>
        </div>
    @elseif($driver->isMissedTripLocked())
        <div class="driver-edit-actions">
            <span class="text-danger small fw-semibold d-block mb-2">Tạm khóa — không nhận chuyến.</span>
            <form method="POST" action="{{ route('operator.drivers.unlock', $driver) }}">
                @csrf
                <button class="btn btn-sm btn-outline-primary">Mở khóa</button>
            </form>
        </div>
    @endif
</div>
