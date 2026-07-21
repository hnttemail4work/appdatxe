@php
    $profile = $profile ?? [];
    $user = $user ?? auth()->user();
    $phone = $profile['phone'] ?? ($user->phone ?? '—');
    $idNumber = $profile['id_number'] ?? ($user->id_number ?? null);
    $displayName = trim((string) ($profile['name'] ?? ''));
    if ($displayName === '' || $displayName === $phone || preg_match('/^[\d\s.+()-]+$/', $displayName)) {
        $displayName = 'Chưa cập nhật';
    }
    $initial = mb_strtoupper(mb_substr($displayName !== 'Chưa cập nhật' ? $displayName : ($phone ?: 'K'), 0, 1));
    $dobLabel = ! empty($profile['date_of_birth'])
        ? \Illuminate\Support\Carbon::parse($profile['date_of_birth'])->format('d/m/Y')
        : null;
@endphp
<div class="driver-profile" aria-label="Thông tin">
    <div class="driver-profile__hero">
        <div class="driver-profile__avatar" aria-hidden="true">
            <span>{{ $initial }}</span>
        </div>
        <div class="driver-profile__identity">
            <p class="driver-profile__name">{{ $displayName }}</p>
            <p class="driver-profile__code">
                <span>Số điện thoại</span>
                <strong>{{ $phone ?: '—' }}</strong>
            </p>
        </div>
    </div>

    <div class="driver-profile__section">
        <h3 class="driver-profile__section-title">Cá nhân</h3>
        <dl class="driver-profile__list">
            <div class="driver-profile__item">
                <dt>Họ tên</dt>
                <dd>{{ $displayName }}</dd>
            </div>
            <div class="driver-profile__item">
                <dt>Giới tính</dt>
                <dd>{{ $profile['gender_label'] ?? 'Chưa cập nhật' }}</dd>
            </div>
            <div class="driver-profile__item">
                <dt>Ngày sinh</dt>
                <dd>{{ $dobLabel ?: 'Chưa cập nhật' }}</dd>
            </div>
            <div class="driver-profile__item">
                <dt>Tuổi</dt>
                <dd>{{ ($profile['age'] ?? null) !== null ? $profile['age'].' tuổi' : 'Chưa cập nhật' }}</dd>
            </div>
        </dl>
    </div>

    <div class="driver-profile__section">
        <h3 class="driver-profile__section-title">Giấy tờ</h3>
        <dl class="driver-profile__list">
            <div class="driver-profile__item">
                <dt>Số CCCD</dt>
                <dd class="driver-profile__mono">{{ $idNumber ?: 'Chưa cập nhật' }}</dd>
            </div>
        </dl>
    </div>
</div>
