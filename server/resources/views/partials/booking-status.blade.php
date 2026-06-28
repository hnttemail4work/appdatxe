{{-- Một nhãn duy nhất — không tách thanh toán / duyệt QL --}}
<span class="badge bg-{{ $booking->primaryStatusColor() }}">{{ $booking->primaryStatusLabel() }}</span>
