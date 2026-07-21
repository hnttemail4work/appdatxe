@php
    $pendingChange = $pendingChange ?? null;
    $frontUrl = $pendingChange?->photoUrl('photo_id_card') ?: $user->idCardPhotoUrl('photo_id_card');
    $backUrl = $pendingChange?->photoUrl('photo_id_card_back') ?: $user->idCardPhotoUrl('photo_id_card_back');
    $frontPending = (bool) $pendingChange?->photoUrl('photo_id_card');
    $backPending = (bool) $pendingChange?->photoUrl('photo_id_card_back');
@endphp
<div class="customer-docs-form" aria-label="Giấy tờ">
    @if($pendingChange)
        <div class="alert alert-warning py-2 small mb-3" role="status">
            Đang có yêu cầu cập nhật chờ duyệt (#{{ $pendingChange->id }}).
            Gửi lại sẽ ghi đè yêu cầu cũ.
        </div>
    @endif

    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="customer-profile-update-form driver-docs-form">
        @csrf

        <details class="driver-docs-accordion" open>
            <summary class="driver-docs-accordion__summary">
                <span class="driver-docs-accordion__title">CCCD</span>
                <span class="driver-docs-accordion__chevron" aria-hidden="true"></span>
            </summary>
            <div class="driver-docs-accordion__body">
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
            </div>
        </details>

        @error('profile')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
        @error('photos')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
        <button type="submit" class="btn btn-warning w-100 fw-semibold driver-docs-submit">Cập nhật</button>
    </form>
</div>
