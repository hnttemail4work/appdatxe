@php
/** @var \App\Models\ScheduleMergeRequest $mergeRequest */
use App\Support\DriverWaitProgress;

$target = $mergeRequest->targetSchedule;
$source = $mergeRequest->sourceSchedule;
$incoming = $source?->driverRelevantBookings() ?? collect();
$waitProgress = DriverWaitProgress::forMergeRequest($mergeRequest);
@endphp

<article class="driver-request-card driver-action-card driver-merge-request-card" data-merge-request-id="{{ $mergeRequest->id }}">
    @include('partials.wait-progress', ['waitProgress' => $waitProgress, 'variant' => 'driver'])
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $target?->route->departure ?? '',
                'to' => $target?->route->destination ?? '',
            ])
            <div class="meta">
                Quản lý đề xuất gom thêm {{ $incoming->count() }} khách vào chuyến của bạn
                @if($target?->shortTripCode())
                    · Mã {{ $target->shortTripCode() }}
                @endif
            </div>
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--pending">Chờ bạn xác nhận</span>
        </div>
    </div>
    <div class="driver-card-body">
        <p class="small text-muted mb-2">Bạn có thể hỏi khách hiện tại trước khi đồng ý ghép thêm.</p>
        @if($incoming->isNotEmpty())
            <div class="driver-passenger-list">
                @foreach($incoming as $booking)
                    <div class="driver-passenger-item {{ ! $loop->last ? 'driver-passenger-item--split' : '' }}">
                        <div class="driver-passenger-head">
                            <strong>{{ $booking->passenger_name ?: 'Hành khách mới' }}</strong>
                            <span class="driver-meta-chip">{{ $booking->pickupTimeLabel() ?? '—' }}</span>
                        </div>
                        <div class="driver-info-line"><span class="driver-info-k">Đón</span> {{ $booking->driverPickupDetailLabel() }}</div>
                        @if($label = $booking->seatCountLabel())
                            <div class="driver-info-line">{{ $label }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="driver-card-actions driver-card-actions--job">
        <form method="POST" action="{{ route('driver.mergeRequests.accept', $mergeRequest) }}" class="driver-accept-form"
              data-confirm="Xác nhận đồng ý gom thêm khách vào chuyến này?"
              data-confirm-title="Đồng ý gom chuyến"
              data-confirm-ok="Đồng ý"
              data-confirm-variant="success">
            @csrf
            <button type="submit" class="btn btn-success driver-btn-accept">Đồng ý gom</button>
        </form>
        <form method="POST" action="{{ route('driver.mergeRequests.reject', $mergeRequest) }}" class="driver-reject-form"
              data-confirm="Từ chối gom — quản lý sẽ xử lý hai chuyến riêng?"
              data-confirm-title="Từ chối gom chuyến"
              data-confirm-variant="danger"
              data-confirm-ok="Từ chối">
            @csrf
            <button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button>
        </form>
    </div>
</article>
