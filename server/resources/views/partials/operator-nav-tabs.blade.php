@php
    $active = $active ?? 'schedule';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = \App\Models\DriverProfile::pendingCountForOperator(auth()->id());
    $pendingSettles = app(\App\Services\DriverWalletService::class)
        ->settlementsAwaitingCodeForOperator(auth()->id())
        ->count();
    $todayCount = $todayCount ?? null;
    $pendingBookings = $pendingBookings ?? null;
    $referralCount = $referralCount ?? null;

    $tabs = [
        ['key' => 'bookings', 'label' => 'Đặt xe gần đây', 'href' => route('operator.dashboard', ['tab' => 'bookings']), 'badge' => $pendingBookings, 'hot' => ($pendingBookings ?? 0) > 0],
        ['key' => 'referrals', 'label' => 'Giới thiệu', 'href' => route('operator.dashboard', ['tab' => 'referrals']), 'badge' => $referralCount],
        ['key' => 'today', 'label' => 'Chuyến hôm nay', 'href' => route('operator.dashboard', ['tab' => 'today']), 'badge' => $todayCount, 'hot' => ($todayCount ?? 0) > 0],
        ['key' => 'wallet', 'label' => 'Ví & kết chuyến', 'href' => route('operator.driverWallet'), 'badge' => $pendingSettles, 'hot' => $pendingSettles > 0],
        ['key' => 'drivers', 'label' => 'Tài xế', 'href' => route('operator.drivers'), 'badge' => $pendingDrivers, 'hot' => $pendingDrivers > 0],
    ];
@endphp
<div class="screen-tabs-wrap operator-nav-tabs mb-0">
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
                        <span class="badge {{ ! empty($tab['hot']) ? 'bg-warning text-dark' : 'bg-secondary' }} ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
