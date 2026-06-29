@extends('layouts.console')

@section('console')
@php
$isEditing = (bool) ($editingTemplate ?? null);
$route = $formData['route'] ?? null;
$vehicle = $formData['vehicle'] ?? [];
@endphp

@include('partials.console-hero', [
    'title' => $isEditing ? 'Sửa chuyến đặt vé' : 'Tạo chuyến đặt vé',
    'subtitle' => 'Mỗi lần lưu một loại xe — khách thấy ngay trên trang đặt vé.',
    'backHref' => route('operator.dashboard'),
    'backLabel' => 'Trang quản lý',
    'actions' => '<a href="' . route('home') . '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Xem trang khách</a>',
])

<div class="console-panel mb-4" id="trip-offer-list">
    <div class="console-panel-head">
        <div class="console-panel-head-accent">
            <h2>Chuyến đang hiển thị</h2>
        </div>
    </div>
    <div class="console-panel-body flush">
        @if($activeOffers->isEmpty())
            <div class="console-empty py-4">
                <p class="mb-0 text-muted small">Chưa có chuyến nào đang hiển thị.</p>
            </div>
        @else
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th>Tuyến</th>
                            <th>Giờ</th>
                            <th>Số chỗ</th>
                            <th>Ngày tạo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeOffers as $offer)
                        @php
                            $editUrl = route('operator.tripOffers.create', ['edit' => $offer->id]) . '#trip-offer-form';
                        @endphp
                        <tr class="@if($isEditing && $editingTemplate->id === $offer->id) is-editing @endif">
                            <td class="cell-primary">{{ $offer->route->departure }} → {{ $offer->route->destination }}</td>
                            <td class="cell-muted">{{ \App\Support\DepartureTimeDisplay::label($offer->departure_time) }}</td>
                            <td>{{ \App\Support\VehicleCapacityOptions::label($offer->vehicle->capacity) }}</td>
                            <td class="cell-muted">{{ $offer->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="console-table-actions">
                                    <a href="{{ $editUrl }}" class="btn btn-outline-primary btn-sm">Sửa</a>
                                    <form method="POST" action="{{ route('operator.tripOffers.destroy', $offer) }}"
                                          data-confirm="Xóa tuyến {{ $offer->route->departure }} → {{ $offer->route->destination }} ({{ substr((string) $offer->departure_time, 0, 5) }}, {{ $offer->vehicle->capacity }} chỗ)? Tuyến sẽ không còn hiển thị trên trang đặt vé."
                                          data-confirm-title="Xóa tuyến"
                                          data-confirm-variant="danger"
                                          data-confirm-ok="Xóa">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $activeOffers])
        @endif
    </div>
</div>

<div class="console-panel" id="trip-offer-form">
    <div class="console-panel-head">
        <div class="console-panel-head-accent">
            <h2>{{ $isEditing ? 'Cập nhật chuyến' : 'Chuyến mới' }}</h2>
            @if($isEditing)
                <p class="subtitle mb-0">
                    {{ $route?->departure }} → {{ $route?->destination }}
                    {{ $formData['departure_time'] ?? '' }}
                    {{ $vehicle['seats'] ?? '' }} chỗ
                </p>
            @endif
        </div>
    </div>
    <div class="console-panel-body">
        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="console-form" id="trip-offer-form-fields" novalidate>
            @csrf
            @if(($formMethod ?? 'POST') === 'PUT')
                @method('PUT')
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="offer-departure">Điểm đi <span class="text-danger">*</span></label>
                    <select name="departure" id="offer-departure" class="form-select @error('departure') is-invalid @enderror" required
                            data-validate-label="Điểm đi">
                        <option value="">— Chọn điểm đi —</option>
                        @include('partials.province-options', ['selected' => old('departure', $route?->departure ?? '')])
                    </select>
                    @error('departure')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="offer-destination">Điểm đến <span class="text-danger">*</span></label>
                    <select name="destination" id="offer-destination" class="form-select @error('destination') is-invalid @enderror" required
                            data-validate-label="Điểm đến">
                        <option value="">— Chọn điểm đến —</option>
                        @include('partials.province-options', ['selected' => old('destination', $route?->destination ?? '')])
                    </select>
                    @error('destination')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    @include('partials.departure-time-input', [
                        'name' => 'departure_time',
                        'id' => 'offer-departure-time',
                        'value' => old('departure_time', $formData['departure_time'] ?? ''),
                        'label' => 'Giờ khởi hành',
                        'required' => true,
                    ])
                </div>
                <div class="col-md-6">
                    @include('partials.departure-time-input', [
                        'name' => 'expected_arrival_time',
                        'id' => 'offer-arrival-time',
                        'label' => 'Giờ dự kiến đến',
                        'value' => old('expected_arrival_time', $formData['expected_arrival_time'] ?? ''),
                        'required' => true,
                    ])
                </div>
            </div>

            <div class="console-panel-head px-0">
                <div class="console-panel-head-accent">
                    <h2>Loại xe &amp; giá</h2>
                </div>
            </div>

            @include('partials.trip-offer-vehicle-fields', ['vehicle' => $vehicle])

            @error('offer')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="d-flex gap-2 flex-wrap mt-3">
                <button type="submit" class="btn btn-primary fw-semibold px-4">Lưu tuyến</button>
                @if($isEditing)
                    <a href="{{ route('operator.tripOffers.create') }}" class="btn btn-outline-primary">Tạo chuyến mới</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    if (window.location.hash === '#trip-offer-form') {
        var anchor = document.getElementById('trip-offer-form');
        if (anchor) {
            window.setTimeout(function () {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 80);
        }
    }

    var form = document.getElementById('trip-offer-form-fields');
    if (form && window.FormFieldValidation) {
        FormFieldValidation.bindClearOnInput(form);
        form.addEventListener('submit', function (e) {
            if (!FormFieldValidation.validateFirst(form)) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@endpush
