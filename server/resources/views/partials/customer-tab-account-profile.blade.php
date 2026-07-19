@php
    $profile = $profile ?? [];
    $phone = $profile['phone'] ?? ($user->phone ?? '—');
    $idNumber = $profile['id_number'] ?? ($user->id_number ?? null);
    $displayName = trim((string) ($profile['name'] ?? ''));
    if ($displayName === '' || $displayName === $phone || preg_match('/^[\d\s.+()-]+$/', $displayName)) {
        $displayName = 'Chưa cập nhật';
    }
@endphp
<section class="customer-account-panel is-active" aria-label="Hồ sơ">
    <div class="customer-account-subhead mb-3">
        <a href="{{ route('customer.account', ['tab' => 'account']) }}" class="customer-account-back" aria-label="Quay lại">←</a>
        <h2 class="customer-account-panel__title mb-0">Hồ sơ</h2>
    </div>

    <div class="customer-account-card">
        <div class="customer-account-profile-rows">
            <div class="customer-account-profile-row">
                <span class="customer-account-profile-row__label">Họ tên</span>
                <strong>{{ $displayName }}</strong>
            </div>
            <div class="customer-account-profile-row">
                <span class="customer-account-profile-row__label">Số điện thoại</span>
                <strong>{{ $phone ?: '—' }}</strong>
            </div>
            <div class="customer-account-profile-row">
                <span class="customer-account-profile-row__label">Giới tính</span>
                <strong>{{ $profile['gender_label'] ?? 'Chưa cập nhật' }}</strong>
            </div>
            <div class="customer-account-profile-row">
                <span class="customer-account-profile-row__label">Tuổi</span>
                <strong>{{ ($profile['age'] ?? null) !== null ? $profile['age'].' tuổi' : 'Chưa cập nhật' }}</strong>
            </div>
            <div class="customer-account-profile-row">
                <span class="customer-account-profile-row__label">Số CCCD</span>
                <strong>{{ $idNumber ?: 'Chưa cập nhật' }}</strong>
            </div>
        </div>

        @if(($profile['photo_id_card_url'] ?? null) || ($profile['photo_id_card_back_url'] ?? null))
            <div class="customer-account-doc-links mt-3">
                @if($profile['photo_id_card_url'] ?? null)
                    <a href="{{ $profile['photo_id_card_url'] }}" target="_blank" rel="noopener">CCCD trước</a>
                @endif
                @if($profile['photo_id_card_back_url'] ?? null)
                    <a href="{{ $profile['photo_id_card_back_url'] }}" target="_blank" rel="noopener">CCCD sau</a>
                @endif
            </div>
        @endif
    </div>
</section>
