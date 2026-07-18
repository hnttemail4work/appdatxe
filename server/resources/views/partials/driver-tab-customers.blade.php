@php
    $customers = $referredCustomers ?? collect();
@endphp
<section class="driver-customers-panel" aria-label="Khách của tôi">
    <h2 class="driver-panel-title mb-3">Khách của tôi</h2>

    @if($customers->isEmpty())
        @include('partials.driver-empty-state', [
            'title' => 'Chưa có khách',
            'hint' => 'Khi khách quét QR mời bạn bè và đặt xe, họ sẽ hiện tại đây.',
        ])
    @else
        <p class="driver-history-intro mb-3">
            <strong>{{ number_format($customers->count()) }}</strong> khách
        </p>
        <ul class="driver-customers-list">
            @foreach($customers as $customer)
                <li class="driver-customers-item">
                    <div class="driver-customers-item__main">
                        <strong>{{ $customer->passenger_name }}</strong>
                        <span class="driver-customers-item__phone">{{ $customer->contact_phone }}</span>
                    </div>
                    <div class="driver-customers-item__meta">
                        <span>{{ (int) $customer->bookings_count }} chuyến</span>
                        @if($customer->last_booked_at)
                            <span>{{ $customer->last_booked_at->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
