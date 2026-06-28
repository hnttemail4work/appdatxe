@php
    $compact = $compact ?? false;
@endphp

@if($driver->isPendingApproval() && auth()->user()->role === 'operator' && ($driver->operator_id === null || $driver->operator_id === auth()->id()))

<div class="d-flex flex-wrap gap-2 align-items-center {{ $compact ? 'justify-content-md-end' : 'mt-2' }}">
    <form method="POST" action="{{ route('operator.drivers.approve', $driver) }}">
        @csrf
        <button class="btn btn-sm btn-primary">Duyệt</button>
    </form>
    <form method="POST" action="{{ route('operator.drivers.reject', $driver) }}"
          data-confirm="Từ chối hồ sơ tài xế này?"
          data-confirm-variant="danger"
          data-confirm-ok="Từ chối">
        @csrf
        <button class="btn btn-sm btn-outline-danger">Từ chối</button>
    </form>
</div>

@endif
