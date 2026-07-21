@php
    $pendingChange = $pendingChange ?? null;
    $profileTab = request('profile_tab', 'info');
    if (! in_array($profileTab, ['info', 'docs'], true)) {
        $profileTab = 'info';
    }
    if ($errors->has('id_number') || $errors->has('photo_id_card') || $errors->has('photo_id_card_back') || $errors->has('profile') || $errors->has('photos')) {
        $profileTab = 'docs';
    }
@endphp
<section class="customer-account-panel is-active customer-profile-hub" aria-label="Hồ sơ khách" data-customer-profile-hub>
    <div class="driver-update-tabs mb-3" role="tablist" aria-label="Mục hồ sơ">
        <button type="button"
                class="driver-update-tabs__btn {{ $profileTab === 'info' ? 'is-active' : '' }}"
                data-customer-profile-tab="info"
                role="tab"
                aria-selected="{{ $profileTab === 'info' ? 'true' : 'false' }}">
            Thông tin
        </button>
        <button type="button"
                class="driver-update-tabs__btn {{ $profileTab === 'docs' ? 'is-active' : '' }}"
                data-customer-profile-tab="docs"
                role="tab"
                aria-selected="{{ $profileTab === 'docs' ? 'true' : 'false' }}">
            Giấy tờ
            @if($pendingChange)
                <span class="driver-update-tabs__badge" aria-label="Đang chờ duyệt">!</span>
            @endif
        </button>
    </div>

    <div class="customer-profile-hub__pane {{ $profileTab === 'info' ? 'is-active' : '' }}"
         data-customer-profile-pane="info"
         @if($profileTab !== 'info') hidden @endif>
        @include('partials.customer-tab-account-info', [
            'user' => $user,
            'profile' => $profile,
        ])
    </div>

    <div class="customer-profile-hub__pane {{ $profileTab === 'docs' ? 'is-active' : '' }}"
         data-customer-profile-pane="docs"
         @if($profileTab !== 'docs') hidden @endif>
        @include('partials.customer-profile-update-form', [
            'user' => $user,
            'pendingChange' => $pendingChange,
        ])
    </div>
</section>
