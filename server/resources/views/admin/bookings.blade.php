@extends('layouts.console')

@section('console')
@php
$bookingList = $bookingList ?? 'active';
$bookingListCounts = $bookingListCounts ?? ['active' => 0, 'completed' => 0, 'feedback' => 0, 'cancelled' => 0];

$bookingListTabs = [
    ['key' => 'active', 'label' => 'Đang chạy', 'badge' => $bookingListCounts['active'] ?? 0],
    ['key' => 'completed', 'label' => 'Hoàn thành', 'badge' => $bookingListCounts['completed'] ?? 0],
    ['key' => 'feedback', 'label' => 'Phản hồi', 'badge' => $bookingListCounts['feedback'] ?? 0],
    ['key' => 'cancelled', 'label' => 'Đã hủy', 'badge' => $bookingListCounts['cancelled'] ?? 0],
];
@endphp

@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'bookings'])

        <div class="screen-tabs-wrap admin-booking-list-tabs mt-3 mb-0">
            <ul class="nav nav-tabs screen-tabs">
                @foreach($bookingListTabs as $tab)
                    <li class="nav-item">
                        <a href="{{ route('admin.bookings', ['list' => $tab['key']]) }}"
                           class="nav-link {{ $bookingList === $tab['key'] ? 'active' : '' }}">
                            {{ $tab['label'] }}
                            @if(($tab['badge'] ?? 0) > 0)
                                <span class="status-pill status-pill--{{ $bookingList === $tab['key'] ? 'accent' : 'neutral' }} ms-1">{{ $tab['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="pt-3">
            @include('partials.admin-booking-list-table', [
                'bookings' => $passengers,
                'drivers' => $drivers,
                'showBulkDelete' => $bookingList === 'cancelled',
                'bookingList' => $bookingList,
                'showAssignActions' => $bookingList === 'active',
            ])
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush

@if($bookingList === 'cancelled')
@push('scripts')
<script>
(function () {
    var bulkForm = document.getElementById('booking-bulk-delete');
    var bulkBtn = document.getElementById('booking-bulk-delete-btn');
    if (!bulkForm || !bulkBtn) return;
    var selectAll = document.getElementById('booking-select-all');
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.booking-select')); };
    function refresh() {
        var checked = boxes().filter(function (el) { return el.checked; });
        bulkBtn.disabled = checked.length < 1;
        if (selectAll) {
            var all = boxes();
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }
    boxes().forEach(function (el) { el.addEventListener('change', refresh); });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes().forEach(function (el) { el.checked = selectAll.checked; });
            refresh();
        });
    }
    refresh();
})();
</script>
@endpush
@endif
