@if($user->hasRejectionNote())
<div class="alert alert-danger mb-3" role="status">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <strong>Lý do từ chối</strong>
            <p class="mb-1 small">{{ $user->rejection_reason }}</p>
            @if($user->rejection_reason_at)
                <div class="small opacity-75">Ghi nhận {{ $user->rejection_reason_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.users.rejection-note.destroy', $user) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-light">Xóa ghi chú</button>
        </form>
    </div>
</div>
@endif
