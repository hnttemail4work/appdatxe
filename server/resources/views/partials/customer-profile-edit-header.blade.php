@php
    /** @var \App\Models\User $user */
    $frontUrl = $user->idCardPhotoUrl('photo_id_card');
@endphp
<div class="driver-edit-header">
    <div class="driver-edit-identity">
        @if($frontUrl)
            <a href="{{ $frontUrl }}" data-photo-zoom title="Bấm để phóng to">
                <img src="{{ $frontUrl }}" alt=""
                     class="driver-edit-avatar rounded-circle object-fit-cover border">
            </a>
        @else
            <div class="driver-edit-avatar driver-edit-avatar-fallback rounded-circle">
                {{ mb_substr($user->preferredDisplayName(), 0, 1) }}
            </div>
        @endif
        <div>
            <h2 class="driver-edit-title">{{ $user->preferredDisplayName() }}</h2>
            <span class="status-pill status-pill--{{ $user->customerDisplayStatusColor() }}">
                {{ $user->customerDisplayStatusLabel() }}
            </span>
            @if($user->phone)
                <span class="driver-meta-code ms-1">{{ $user->phone }}</span>
            @endif
        </div>
    </div>

    @if($user->approval_status === \App\Models\User::APPROVAL_REJECTED)
        <div class="driver-edit-actions">
            <span class="text-danger small fw-semibold d-block">Hồ sơ đã từ chối — khách không đặt xe được.</span>
        </div>
    @endif
</div>
