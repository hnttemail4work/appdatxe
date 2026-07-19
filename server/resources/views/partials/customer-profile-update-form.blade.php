@php
    $pendingChange = $pendingChange ?? null;
    $frontUrl = $pendingChange?->photoUrl('photo_id_card') ?: $user->idCardPhotoUrl('photo_id_card');
    $backUrl = $pendingChange?->photoUrl('photo_id_card_back') ?: $user->idCardPhotoUrl('photo_id_card_back');
    $frontPending = (bool) $pendingChange?->photoUrl('photo_id_card');
    $backPending = (bool) $pendingChange?->photoUrl('photo_id_card_back');
@endphp
<section class="customer-account-panel is-active" aria-label="Cập nhật CCCD">
    <div class="customer-account-subhead mb-3">
        <a href="{{ route('customer.account', ['tab' => 'account']) }}" class="customer-account-back" aria-label="Quay lại">←</a>
        <h2 class="customer-account-panel__title mb-0">Cập nhật CCCD</h2>
    </div>

    <div class="customer-account-card">
        <p class="small text-muted mb-3">Gửi ảnh CCCD mới cho admin duyệt. Ảnh hiện tại vẫn giữ đến khi được duyệt.</p>

        @if($pendingChange)
            <div class="alert alert-warning py-2 small mb-3" role="status">
                Đang có yêu cầu cập nhật chờ duyệt (#{{ $pendingChange->id }}).
                Gửi lại sẽ ghi đè yêu cầu cũ.
            </div>
        @endif

        @if($frontUrl || $backUrl)
            <div class="mb-3">
                <p class="form-label mb-2">Ảnh hiện tại{{ ($frontPending || $backPending) ? ' / đang chờ duyệt' : '' }}</p>
                <div class="customer-account-doc-thumbs">
                    @if($frontUrl)
                        <a href="{{ $frontUrl }}" target="_blank" rel="noopener"
                           class="customer-account-doc-thumb {{ $frontPending ? 'is-pending' : '' }}">
                            <img src="{{ $frontUrl }}" alt="CCCD trước" loading="lazy">
                            <span>Trước</span>
                        </a>
                    @endif
                    @if($backUrl)
                        <a href="{{ $backUrl }}" target="_blank" rel="noopener"
                           class="customer-account-doc-thumb {{ $backPending ? 'is-pending' : '' }}">
                            <img src="{{ $backUrl }}" alt="CCCD sau" loading="lazy">
                            <span>Sau</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="customer-profile-update-form">
            @csrf
            <div class="mb-3">
                <p class="form-label mb-2">Ảnh CCCD mới <span class="text-danger">*</span></p>
                @include('partials.customer-docs-upload-register', [
                    'idCardRequired' => true,
                    'inputIdPrefix' => 'cust-update',
                ])
            </div>
            @error('profile')<div class="alert alert-danger py-2 small mb-0">{{ $message }}</div>@enderror
            @error('photos')<div class="alert alert-danger py-2 small mb-0">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary w-100 mt-2">Gửi yêu cầu duyệt</button>
        </form>
    </div>
</section>
