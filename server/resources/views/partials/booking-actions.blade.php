@if(in_array($booking->booking_status, ['pending']) || ($booking->payment_status === 'unpaid' && !in_array($booking->booking_status, ['cancelled', 'rejected'])))

    <div class="d-flex flex-column gap-1">

        @if($booking->payment_status === 'unpaid')

            <form method="POST" action="{{ route($routePrefix . '.bookings.confirmPayment', $booking) }}">

                @csrf

                <button class="btn btn-sm btn-success w-100"

                    title="{{ $booking->hasPendingPaymentClaim() ? 'Khách đã báo chuyển khoản — quản lý xác nhận' : 'Xác nhận đã thu tiền' }}">

                    {{ $booking->hasPendingPaymentClaim() ? '✓ Xác nhận đã TT' : 'Xác nhận thanh toán' }}

                </button>

            </form>

        @endif



        @if($booking->payment_status === 'paid' && $booking->booking_status === 'pending')

            <form method="POST" action="{{ route($routePrefix . '.bookings.accept', $booking) }}">

                @csrf

                <button class="btn btn-sm btn-primary w-100">Duyệt chuyến → Tài xế</button>

            </form>

        @endif



        @if($booking->booking_status === 'pending')

            <form method="POST" action="{{ route($routePrefix . '.bookings.reject', $booking) }}"

                onsubmit="return confirm('Từ chối booking này?')">

                @csrf

                <button class="btn btn-sm btn-outline-danger w-100">Từ chối</button>

            </form>

        @endif

    </div>

@elseif($booking->booking_status === 'confirmed')

    @if($booking->trip_status === 'completed')

        <span class="badge bg-success">✓ Hoàn tất</span>

    @elseif($booking->trip_status === 'awaiting_completion')

        <span class="badge bg-info text-dark">Chờ KH xác nhận</span>

    @else

        <span class="badge bg-primary">Đã duyệt — tài xế nhận</span>

    @endif

@else

    <span class="text-muted small">—</span>

@endif

