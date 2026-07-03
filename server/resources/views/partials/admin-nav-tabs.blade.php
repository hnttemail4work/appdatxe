@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = (int) \App\Models\DriverProfile::query()->pendingApproval()->count();
    $pendingDeposits = (int) \App\Models\DriverWalletTransaction::query()
        ->where('type', 'deposit')
        ->where('status', 'pending')
        ->count();

    $tabs = [
        ['key' => 'config', 'label' => 'Cấu hình', 'href' => route('admin.dashboard'), 'badge' => null, 'hot' => false],
        ['key' => 'bookings', 'label' => 'Đặt xe', 'href' => route('admin.bookings'), 'badge' => null, 'hot' => false],
        ['key' => 'drivers', 'label' => 'Tài xế', 'href' => route('admin.drivers'), 'badge' => $pendingDrivers ?: null, 'hot' => $pendingDrivers > 0],
        ['key' => 'deposits', 'label' => 'Nạp ví', 'href' => route('admin.driverWallet'), 'badge' => $pendingDeposits ?: null, 'hot' => $pendingDeposits > 0],
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
