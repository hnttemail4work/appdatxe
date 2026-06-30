@if($driver->hasRejectionNote())
<div class="driver-rejection-note alert alert-danger-subtle border border-danger-subtle mb-3">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="flex-grow-1">
            <div class="fw-semibold small text-danger mb-1">Lý do từ chối</div>
            <p class="mb-1 small">{{ $driver->rejection_reason }}</p>
            @if($driver->rejection_reason_at)
                <div class="text-muted small">Ghi nhận {{ $driver->rejection_reason_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>
        @if(auth()->user()->role === 'operator')
        <form method="POST" action="{{ route('operator.drivers.rejection-note.destroy', $driver) }}"
              data-confirm="Xóa ghi chú từ chối này?"
              data-confirm-title="Xóa ghi chú"
              data-confirm-variant="danger"
              data-confirm-ok="Xóa">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa ghi chú">×</button>
        </form>
        @endif
    </div>
</div>
@endif
