@extends('layouts.console')



@section('console')

@php

$bookingList = $bookingList ?? 'active';

$bookingListCounts = $bookingListCounts ?? ['active' => 0, 'completed' => 0, 'feedback' => 0, 'cancelled' => 0];



$bookingListTabs = [

    ['key' => 'active', 'label' => 'Chuyến', 'badge' => $bookingListCounts['active'] ?? 0],

    ['key' => 'completed', 'label' => 'Hoàn thành', 'badge' => $bookingListCounts['completed'] ?? 0],

    ['key' => 'feedback', 'label' => 'Phản hồi', 'badge' => $bookingListCounts['feedback'] ?? 0],

    ['key' => 'cancelled', 'label' => 'Đã hủy', 'badge' => $bookingListCounts['cancelled'] ?? 0],

];

@endphp



@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])



<div class="console-panel">

    <div class="console-panel-body"

         id="admin-bookings-sync-root"

         data-admin-bookings-sync-url="{{ route('admin.bookings.sync') }}"

         data-booking-list="{{ $bookingList }}"

         data-admin-bookings-poll-ms="5000">

        @include('partials.admin-nav-tabs', ['active' => 'bookings'])



        <div class="admin-bookings-sync-bar mt-3 mb-0">

            <span class="admin-bookings-sync-status" id="admin-bookings-sync-status" aria-live="polite">

                Đồng bộ tự động mỗi 15 giây

            </span>

        </div>



        <div id="admin-bookings-alert-offduty"

             class="alert alert-warning d-flex align-items-start gap-2 mt-2 mb-0{{ ($catalogOffDutyBookingCount ?? 0) > 0 && $bookingList === 'active' ? '' : ' d-none' }}"

             role="alert">

            @if(($catalogOffDutyBookingCount ?? 0) > 0 && $bookingList === 'active')

                <span class="fw-semibold">⚠</span>

                <div>

                    <strong>{{ $catalogOffDutyBookingCount }} đơn</strong> khách chọn tài xế nhưng tài xế đó

                    <strong>chưa bật Sẵn sàng</strong> — xem cột <strong>Thời gian chờ</strong> để gán tài xế khác.

                </div>

            @endif

        </div>



        <div id="admin-bookings-alert-late"

             class="alert alert-danger d-flex align-items-start gap-2 mt-2 mb-0{{ ($latePickupAlertCount ?? 0) > 0 && $bookingList === 'active' ? '' : ' d-none' }}"

             role="alert">

            @if(($latePickupAlertCount ?? 0) > 0 && $bookingList === 'active')

                <span class="fw-semibold">⏱</span>

                <div>

                    <strong>{{ $latePickupAlertCount }} chuyến</strong> có nguy cơ trễ hoặc đã quá giờ đón —

                    xem cột <strong>Cảnh báo</strong> hoặc <strong>Thời gian chờ</strong>.

                </div>

            @endif

        </div>



        <div class="screen-tabs-wrap admin-booking-list-tabs mt-3 mb-0">

            <ul class="nav nav-tabs screen-tabs">

                @foreach($bookingListTabs as $tab)

                    <li class="nav-item">

                        <a href="{{ route('admin.bookings', ['list' => $tab['key']]) }}"

                           class="nav-link {{ $bookingList === $tab['key'] ? 'active' : '' }}"

                           data-booking-tab="{{ $tab['key'] }}">

                            {{ $tab['label'] }}

                            @if(($tab['badge'] ?? 0) > 0)

                                <span class="status-pill status-pill--{{ $bookingList === $tab['key'] ? 'accent' : 'neutral' }} ms-1"

                                      data-booking-tab-count>{{ $tab['badge'] }}</span>

                            @endif

                        </a>

                    </li>

                @endforeach

            </ul>

        </div>



        <div class="pt-3" id="admin-bookings-list-panel">

            @include('partials.admin-booking-list-table', [

                'bookings' => $passengers,

                'drivers' => $drivers,

                'showBulkDelete' => $bookingList === 'cancelled',

                'bookingList' => $bookingList,

                'showAssignActions' => false,

                'showWaitingColumn' => $bookingList === 'active',

            ])

        </div>

    </div>

</div>

@endsection



@push('styles')

<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">

<style>

.admin-bookings-sync-bar {

    display: flex;

    justify-content: flex-end;

}

.admin-bookings-sync-status {

    font-size: .75rem;

    color: var(--bs-secondary-color, #9ca3af);

}

.admin-bookings-sync-status.is-syncing {

    opacity: .65;

}

</style>

@endpush



@push('scripts')

<script src="{{ asset('js/admin-bookings-sync.js') }}?v={{ filemtime(public_path('js/admin-bookings-sync.js')) }}"></script>

@if($bookingList === 'cancelled')

<script>

(function () {

    function bindBulkDeleteControls() {

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

    }



    bindBulkDeleteControls();

    document.addEventListener('admin-bookings:bulk-controls', bindBulkDeleteControls);

})();

</script>

@endif

@endpush

