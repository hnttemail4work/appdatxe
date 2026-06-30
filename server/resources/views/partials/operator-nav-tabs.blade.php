@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = \App\Models\DriverProfile::pendingCountForOperator(auth()->id());
    $walletSvc = app(\App\Services\DriverWalletService::class);
    $walletCounts = $walletSvc->pendingWalletRequestCounts(auth()->id());
    $pendingBookings = $pendingBookings ?? null;

    $tabs = [
        ['key' => 'bookings', 'label' => 'Vận hành chuyến', 'href' => route('operator.dashboard'), 'badge' => $pendingBookings, 'hot' => ($pendingBookings ?? 0) > 0],
        ['key' => 'deposits', 'label' => 'Nạp ví', 'href' => route('operator.driverWallet'), 'badge' => $walletCounts['deposits'] ?: null, 'hot' => ($walletCounts['deposits'] ?? 0) > 0],
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
                        <span class="status-pill status-pill--{{ ! empty($tab['hot']) ? 'accent' : 'neutral' }} ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</div>
