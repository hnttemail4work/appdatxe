{{-- Tab Tài khoản → Lịch sử chuyến (chỉ chuyến đã hoàn thành). --}}
<section class="customer-account-panel is-active customer-completed-trips" id="customer-completed-trips" aria-label="Lịch sử chuyến đi">
    <div class="customer-completed-trips__head">
        <h2 class="customer-completed-trips__title">Lịch sử chuyến đi</h2>
        @if(! empty($completedTrips) && $completedTrips->total() > 0)
            <span class="customer-completed-trips__count">{{ $completedTrips->total() }} chuyến</span>
        @endif
    </div>

    @if(! empty($completedTripRows))
        <div class="customer-completed-trips__list">
            @foreach($completedTripRows as $trip)
                @include('partials.customer-trip-card', ['trip' => $trip])
            @endforeach
        </div>
        @include('partials.pagination', ['paginator' => $completedTrips])
    @else
        <div class="customer-account-card">
            <p class="customer-completed-trips__empty mb-0">Chưa có chuyến hoàn thành. Khi hết cuốc, lịch sử sẽ hiện tại đây.</p>
        </div>
    @endif
</section>
