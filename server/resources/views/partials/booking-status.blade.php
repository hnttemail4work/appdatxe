{{-- Một nhãn duy nhất — không tách thanh toán / duyệt QL --}}
<span class="status-pill status-pill--{{ $booking->primaryStatusColor() }}">{{ $booking->primaryStatusLabel() }}</span>
