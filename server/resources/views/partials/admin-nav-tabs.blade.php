@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $badges = app(\App\Services\AdminActionBadgeService::class);

    $driversActionCount = $badges->driversBadgeVisible() ? $badges->driversBadgeCount() : 0;
    $usersActionCount = $badges->usersBadgeVisible() ? $badges->usersBadgeCount() : 0;
    $pendingWalletDeposits = $badges->walletDepositsBadgeVisible() ? $badges->walletDepositsBadgeCount() : 0;

    $tabs = [
        ['key' => 'bookings', 'label' => 'Đặt xe', 'href' => route('admin.bookings'), 'badge' => null, 'hot' => false],
        ['key' => 'revenue', 'label' => 'Doanh thu', 'href' => route('admin.revenue'), 'badge' => null, 'hot' => false],
        [
            'key' => 'drivers',
            'label' => 'Tài xế',
            'href' => $badges->driversBadgeCount() > 0 ? route('admin.drivers', ['filter' => 'pending']) : route('admin.drivers'),
            'badge' => $driversActionCount ?: null,
            'hot' => $driversActionCount > 0,
        ],
        [
            'key' => 'users',
            'label' => 'Khách hàng',
            'href' => $badges->usersBadgeCount() > 0 ? route('admin.users', ['status' => 'pending']) : route('admin.users'),
            'badge' => $usersActionCount ?: null,
            'hot' => $usersActionCount > 0,
        ],
        [
            'key' => 'wallet-deposits',
            'label' => 'Nạp ví',
            'href' => route('admin.walletDeposits'),
            'badge' => $pendingWalletDeposits ?: null,
            'hot' => $pendingWalletDeposits > 0,
        ],
        ['key' => 'qr', 'label' => 'QR', 'href' => route('admin.referrals'), 'badge' => null, 'hot' => false],
        ['key' => 'driver-inbox', 'label' => 'Tin tài xế', 'href' => route('admin.driverInbox'), 'badge' => null, 'hot' => false],
        ['key' => 'auth-codes', 'label' => 'OTP / Reset', 'href' => route('admin.authCodes'), 'badge' => null, 'hot' => false],
        ['key' => 'config', 'label' => 'Cấu hình', 'href' => route('admin.dashboard'), 'badge' => null, 'hot' => false],
    ];
@endphp
<div class="screen-tabs-wrap admin-nav-tabs mb-0">
    <ul class="nav nav-tabs screen-tabs">
        @foreach($tabs as $tab)
            @php
                $isActive = $active === $tab['key'];
                $isParent = $isActive && $tab['key'] === 'drivers' && $driversSubpage;
                $linkClass = $isParent ? 'active-parent' : ($isActive ? 'active' : '');
            @endphp
            <li class="nav-item">
                <a href="{{ $tab['href'] }}" class="nav-link {{ $linkClass }}">
                    {{ $tab['label'] }}
                    @if(! empty($tab['badge']))
                        <span class="status-pill status-pill--{{ ! empty($tab['hot']) ? 'accent' : 'neutral' }} ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
