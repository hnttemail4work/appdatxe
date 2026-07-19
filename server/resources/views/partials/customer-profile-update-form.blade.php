@php
    $pendingChange = $pendingChange ?? null;
    $frontUrl = $pendingChange?->photoUrl('photo_id_card') ?: $user->idCardPhotoUrl('photo_id_card');
    $backUrl = $pendingChange?->photoUrl('photo_id_card_back') ?: $user->idCardPhotoUrl('photo_id_card_back');
    $frontPending = (bool) $pendingChange?->photoUrl('photo_id_card');
    $backPending = (bool) $pendingChange?->photoUrl('photo_id_card_back');
@endphp
<section class="customer-account-panel is-active" aria-label="Cập nhật CCCD">
    <div class="customer-account-card">
        <p class="small text-muted mb-3">Xem ảnh hiện tại, bấm «Thay ảnh» chọn ảnh mới rồi gửi — ảnh cũ giữ đến khi admin duyệt.</p>

        @if($pendingChange)
            <div class="alert alert-warning py-2 small mb-3" role="status">
                Đang có yêu cầu cập nhật chờ duyệt (#{{ $pendingChange->id }}).
                Gửi lại sẽ ghi đè yêu cầu cũ.
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="customer-profile-update-form">
            @csrf
            @include('partials.photo-upload-slots', [
                'columnsClass' => 'row g-2 row-cols-2',
                'slots' => [
                    [
                        'field' => 'photo_id_card',
                        'label' => 'CCCD trước',
                        'url' => $frontUrl,
                        'ratio' => 'landscape',
                        'required' => true,
                        'badge' => $frontPending ? 'Chờ duyệt' : null,
                    ],
                    [
                        'field' => 'photo_id_card_back',
                        'label' => 'CCCD sau',
                        'url' => $backUrl,
                        'ratio' => 'landscape',
                        'required' => true,
                        'badge' => $backPending ? 'Chờ duyệt' : null,
                    ],
                ],
            ])
            @error('profile')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
            @error('photos')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary w-100 mt-3">Gửi yêu cầu duyệt</button>
        </form>
    </div>
</section>
