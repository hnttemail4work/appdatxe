@php
    $active = $active ?? 'bookings';
    $driversSubpage = $driversSubpage ?? false;
    $pendingDrivers = (int) \App\Models\DriverProfile::query()->pendingApproval()->count();
    $pendingDocUpdates = (int) \App\Models\DriverProfile::query()->pendingDocumentUpdate()->count();
    $driversActionCount = $pendingDrivers + $pendingDocUpdates;
    $pendingDeposits = (int) \App\Models\DriverWalletTransaction::query()
        ->where('type', 'deposit')
        ->where('status', 'pending')
        ->count();
    $pendingCustomerDeposits = (int) \App\Models\CustomerWalletTransaction::query()
        ->where('type', 'deposit')
        ->where('status', 'pending')
        ->count();
    $pendingCustomers = (int) \App\Models\User::query()
        ->where('role', 'customer')
        ->where('approval_status', \App\Models\User::APPROVAL_PENDING)
        ->count();
    $pendingCustomerUpdates = (int) \App\Models\CustomerProfileChangeRequest::query()
        ->where('status', \App\Models\CustomerProfileChangeRequest::STATUS_PENDING)
        ->count();
    $usersActionCount = $pendingCustomers + $pendingCustomerUpdates;

    $tabs = [
        ['key' => 'bookings', 'label' => 'Đặt xe', 'href' => route('admin.bookings'), 'badge' => null, 'hot' => false],
        ['key' => 'revenue', 'label' => 'Doanh thu', 'href' => route('admin.revenue'), 'badge' => null, 'hot' => false],
        ['key' => 'drivers', 'label' => 'Tài xế', 'href' => route('admin.drivers'), 'badge' => $driversActionCount ?: null, 'hot' => $driversActionCount > 0],
        ['key' => 'users', 'label' => 'Khách hàng', 'href' => route('admin.users'), 'badge' => $usersActionCount ?: null, 'hot' => $usersActionCount > 0],
        ['key' => 'deposits', 'label' => 'Nạp ví TX', 'href' => route('admin.driverWallet'), 'badge' => $pendingDeposits ?: null, 'hot' => $pendingDeposits > 0],
        ['key' => 'customer-deposits', 'label' => 'Nạp ví KH', 'href' => route('admin.customerWallet'), 'badge' => $pendingCustomerDeposits ?: null, 'hot' => $pendingCustomerDeposits > 0],
        ['key' => 'referrals', 'label' => 'Giới thiệu', 'href' => route('admin.referrals'), 'badge' => null, 'hot' => false],
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
