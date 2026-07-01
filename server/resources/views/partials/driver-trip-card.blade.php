@php
/** @var array<string, mixed> $card */
$isOpenTrip = ! empty($card['is_open_trip']);
$acceptUrl = $card['accept_url'] ?? $card['claim_url'] ?? '#';
$passengers = collect($card['passengers'] ?? []);
@endphp
<div class="driver-request-card driver-action-card" data-request-id="{{ $card['id'] ?? '' }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @php
                $routeParts = explode(' → ', (string) ($card['route'] ?? ''));
            @endphp
            @include('partials.driver-route-head', [
                'from' => $routeParts[0] ?? '',
                'to' => $routeParts[1] ?? ($card['route'] ?? ''),
            ])
            <div class="driver-card-meta-row">
                @if(! empty($card['meta_label']))
                    <span class="driver-meta-chip">{{ $card['meta_label'] }}</span>
                @elseif(! empty($card['departure_time']))
                    <span class="driver-meta-chip">{{ $card['departure_time'] }}</span>
                @endif
                @if(($card['passenger_count'] ?? 0) > 1)
                    <span class="driver-meta-chip">{{ $card['passenger_count'] }} khách</span>
                @endif
                @if(! empty($card['distance_label']))
                    <span class="driver-meta-chip driver-meta-chip--distance">📍 {{ $card['distance_label'] }}</span>
                @endif
                @if(! empty($card['expires_in_label']))
                    <span class="driver-meta-chip driver-meta-chip--warn">⏱ {{ $card['expires_in_label'] }}</span>
                @endif
            </div>
            @if(! empty($card['trip_code']))
                <div class="meta driver-schedule-trip-code">Mã <code class="driver-trip-code">{{ $card['trip_code'] }}</code></div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            @if(! empty($card['trip_total']))
                <div class="driver-fare-badge">
                    <span class="driver-fare-label">Tổng</span>
                    <span class="driver-fare-amount">{{ $card['trip_total'] }} đ</span>
                </div>
            @endif
            <span class="status-pill status-pill--accent">{{ $isOpenTrip ? 'Cuốc gần bạn' : 'Cuốc mới' }}</span>
        </div>
    </div>
    <div class="driver-card-body">
        @if($passengers->isNotEmpty())
            <div class="driver-passenger-list">
                @foreach($passengers as $passenger)
                    @include('partials.driver-passenger-snippet', ['passenger' => $passenger, 'isLast' => $loop->last])
                @endforeach
            </div>
        @else
            <p class="text-muted small mb-0">Chưa có chi tiết hành khách.</p>
        @endif
    </div>
    <div class="driver-card-actions driver-card-actions--job">
        <form method="POST" action="{{ $acceptUrl }}" class="driver-accept-form">@csrf
            <button type="submit" class="btn btn-success driver-btn-accept">Nhận cuốc</button>
        </form>
        @if(! $isOpenTrip && ! empty($card['reject_url']))
            <form method="POST" action="{{ $card['reject_url'] }}" class="driver-reject-form">@csrf
                <button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button>
            </form>
        @endif
    </div>
</div>
