@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = (int) \App\Models\DriverProfile::query()->pendingApproval()->count();
    $pendingDeposits = (int) \App\Models\DriverWalletTransaction::query()
        ->where('type', 'deposit')
        ->where('status', 'pending')
        ->count();
    $activeBookingReferralCount = (int) \App\Models\ReferralCode::query()
        ->where('type', \App\Models\ReferralCode::TYPE_BOOKING_TEMP)
        ->where('status', \App\Models\ReferralCode::STATUS_ACTIVE)
        ->count();

    $tabs = [
        ['key' => 'bookings', 'label' => 'Đặt xe', 'href' => route('admin.bookings'), 'badge' => null, 'hot' => false],
        ['key' => 'revenue', 'label' => 'Doanh thu', 'href' => route('admin.revenue'), 'badge' => null, 'hot' => false],
        ['key' => 'drivers', 'label' => 'Tài xế', 'href' => route('admin.drivers'), 'badge' => $pendingDrivers ?: null, 'hot' => $pendingDrivers > 0],
        ['key' => 'users', 'label' => 'Khách hàng', 'href' => route('admin.users'), 'badge' => null, 'hot' => false],
        ['key' => 'deposits', 'label' => 'Nạp ví', 'href' => route('admin.driverWallet'), 'badge' => $pendingDeposits ?: null, 'hot' => $pendingDeposits > 0],
        ['key' => 'referrals', 'label' => 'Mã giới thiệu', 'href' => route('admin.referrals'), 'badge' => $activeBookingReferralCount ?: null, 'hot' => $activeBookingReferralCount > 0],
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
