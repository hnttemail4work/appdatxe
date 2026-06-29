@extends('layouts.console')

@section('console')
@php
$drivers = $drivers ?? collect();
@endphp

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.operator-nav-tabs', ['active' => 'drivers'])

@if($drivers->isEmpty())
        <div class="console-empty py-5">
            <div class="console-empty-icon">👤</div>
            <p class="mb-0">Chưa có tài xế.</p>
        </div>
@else
        <div class="driver-mgmt-panel pt-3">
    <div class="console-panel-head driver-mgmt-head px-0 pt-0">
        <div class="console-panel-head-accent">
            <h2>Danh sách</h2>
        </div>
    </div>
    <div class="console-panel-body flush">
        <div class="console-table-wrap">
            <table class="console-table driver-mgmt-table">
                <thead>
                    <tr>
                        <th>Tài xế</th>
                        <th>Số điện thoại</th>
                        <th>Xe</th>
                        <th>Trạng thái</th>
                        <th class="text-end" style="width:11rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drivers as $d)
                    <tr class="{{ $d->isPendingApproval() ? 'driver-row-pending' : '' }}">
                        <td>
                            <div class="driver-mgmt-name">
                                @if($d->photo_portrait)
                                    <img src="{{ $d->photoUrl('photo_portrait') }}" alt=""
                                         class="driver-mgmt-avatar rounded-circle object-fit-cover border">
                                @else
                                    <div class="driver-mgmt-avatar driver-mgmt-avatar-fallback">
                                        {{ mb_substr($d->user->name, 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="cell-primary">{{ $d->user->name }}</div>
                                    @if($d->driver_code)
                                        <div class="driver-meta-code">{{ $d->driver_code }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="cell-muted">{{ $d->user->phone ?? '—' }}</td>
                        <td class="cell-muted">
                            @if($d->vehicle_license_plate)
                                <strong class="text-white">{{ $d->vehicle_license_plate }}</strong>
                                @if($d->vehicle_type)
                                    <span class="driver-meta-sub ms-1">{{ ucfirst($d->vehicle_type) }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="status-pill status-pill--{{ $d->displayStatusColor() }}">{{ $d->displayStatusLabel() }}</span>
                            @if($d->missedTripStrikeLabel() && ! $d->isMissedTripLocked() && ! $d->isPendingApproval() && ! $d->isRejected())
                                <div class="mt-1"><span class="status-pill status-pill--pending">{{ $d->missedTripStrikeLabel() }}</span></div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                @if($d->driver_code)
                                    @include('partials.share-booking-qr-button', [
                                        'shareUrl' => \App\Support\BookingShareUrl::guest($d->driver_code),
                                        'shareLabel' => 'QR đặt vé — ' . $d->user->name,
                                        'modalId' => 'shareQrDriver-' . $d->id,
                                        'iconOnly' => true,
                                    ])
                                @endif
                                <a href="{{ route('operator.drivers.edit', $d) }}" class="btn btn-sm {{ $d->isPendingApproval() ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Xem hồ sơ
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $drivers])
    </div>
        </div>
@endif
    </div>
</div>
@endsection

@push('modals')
    @foreach($drivers as $d)
        @if($d->driver_code)
            @include('partials.share-booking-qr-modal', [
                'shareUrl' => \App\Support\BookingShareUrl::guest($d->driver_code),
                'shareLabel' => 'QR đặt vé — ' . $d->user->name,
                'modalId' => 'shareQrDriver-' . $d->id,
            ])
        @endif
    @endforeach
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('css/driver-mgmt.css') }}">
@endpush
