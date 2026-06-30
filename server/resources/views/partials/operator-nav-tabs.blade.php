@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = \App\Models\DriverProfile::pendingCountForOperator(auth()->id());
    $walletSvc = app(\App\Services\DriverWalletService::class);
    $walletCounts = $walletSvc->pendingWalletRequestCounts(auth()->id());
    $codesIssuedCount = $walletSvc->codesAwaitingDriverForOperator(auth()->id())->count();
    $pendingBookings = $pendingBookings ?? null;

    $tabs = [
        ['key' => 'bookings', 'label' => 'Đặt xe gần đây', 'href' => route('operator.dashboard'), 'badge' => $pendingBookings, 'hot' => ($pendingBookings ?? 0) > 0],
        ['key' => 'deposits', 'label' => 'Nạp ví', 'href' => route('operator.driverWallet', ['tab' => 'deposits']), 'badge' => $walletCounts['deposits'] ?: null, 'hot' => ($walletCounts['deposits'] ?? 0) > 0],
        ['key' => 'settlements', 'label' => 'Kết chuyến', 'href' => route('operator.driverWallet', ['tab' => 'settlements']), 'badge' => $walletCounts['settlements'] ?: null, 'hot' => ($walletCounts['settlements'] ?? 0) > 0],
    ];

    if ($codesIssuedCount > 0) {
        $tabs[] = [
            'key' => 'issued',
            'label' => 'Mã đã cấp',
            'href' => route('operator.driverWallet', ['tab' => 'issued']),
            'badge' => $codesIssuedCount,
        ];
    }

    $tabs[] = ['key' => 'drivers', 'label' => 'Tài xế', 'href' => route('operator.drivers'), 'badge' => $pendingDrivers, 'hot' => $pendingDrivers > 0];
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
