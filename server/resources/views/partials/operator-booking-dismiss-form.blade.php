<form method="POST"
      action="{{ route('operator.bookings.dismissStuck', $booking) }}"
      data-confirm="{{ $booking->operatorDismissConfirmMessage() }}"
      data-confirm-title="Ẩn khỏi danh sách"
      data-confirm-variant="danger"
      data-confirm-ok="Ẩn">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-secondary">Ẩn</button>
</form>
