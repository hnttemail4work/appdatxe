@extends('layouts.console')

@section('console')
@php
$isEditing = (bool) ($editingTemplate ?? null);
$route = $formData['route'] ?? null;
$vehicle = $formData['vehicle'] ?? [];
$bookingPageUrl = url(route('home'));
$bookingQrAction = view('partials.booking-qr-trigger', ['url' => $bookingPageUrl])->render();
@endphp

@include('partials.console-hero', [
    'title' => $isEditing ? 'Sửa chuyến đặt vé' : 'Tạo chuyến đặt vé',
    'subtitle' => 'Mỗi lần lưu một loại xe — khách thấy ngay trên trang đặt vé.',
    'actions' => $bookingQrAction,
])

@php
$quickTrip = $quickTrip ?? [];
$quickServiceDate = old('service_date', $quickTrip['service_date'] ?? now()->toDateString());
$quickServiceDateLabel = \Carbon\Carbon::parse($quickServiceDate)->format('d/m/Y');
$quickVehiclePhotos = $quickTrip['vehicle_photos'] ?? [];
$quickSeats = (int) old('seats', 9);
$quickHasPhoto = ! empty($quickVehiclePhotos[$quickSeats]);
@endphp

<div class="console-panel mb-4" id="trip-offer-quick">
    <div class="console-panel-head">
        <div class="console-panel-head-accent">
            <h2>Tạo chuyến nhanh</h2>
            <p class="subtitle mb-0">Chọn điểm đi — hệ thống tự tạo tất cả tuyến đến (km/giá theo admin).</p>
        </div>
    </div>
    <div class="console-panel-body">
        @error('quick_trip')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('vehicle_photo')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('operator.tripOffers.bulkQuick') }}" class="console-form"
              enctype="multipart/form-data"
              data-confirm="Tạo tất cả tuyến từ điểm đi đã chọn cho ngày {{ $quickServiceDateLabel }}?"
              data-confirm-title="Tạo chuyến nhanh"
              data-confirm-ok="Tạo tất cả">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="quick-departure">Điểm đi <span class="text-danger">*</span></label>
                    <select name="departure" id="quick-departure" class="form-select" required>
                        @include('partials.province-options', [
                            'selected' => old('departure', $quickTrip['default_departure'] ?? \App\Support\LocationCatalog::hub()),
                        ])
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="quick-service-date">Ngày chạy <span class="text-danger">*</span></label>
                    <input type="date" name="service_date" id="quick-service-date" class="form-control @error('service_date') is-invalid @enderror"
                           value="{{ $quickServiceDate }}" min="{{ now()->toDateString() }}" required>
                    @error('service_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    @include('partials.departure-time-input', [
                        'name' => 'departure_time',
                        'id' => 'quick-departure-time',
                        'value' => old('departure_time', ''),
                        'label' => 'Giờ khởi hành',
                        'required' => false,
                    ])
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="quick-seats">Số chỗ <span class="text-danger">*</span></label>
                    <select name="seats" id="quick-seats" class="form-select" required>
                        @include('partials.vehicle-capacity-options', ['selected' => $quickSeats])
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary fw-semibold w-100">Tạo tất cả</button>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="quick-vehicle-photo">
                        Ảnh xe @if(! $quickHasPhoto)<span class="text-danger">*</span>@endif
                    </label>
                    <input type="file" name="vehicle_photo" id="quick-vehicle-photo"
                           class="form-control @error('vehicle_photo') is-invalid @enderror"
                           accept="image/jpeg,image/png,image/webp,image/*"
                           @if(! $quickHasPhoto) required @endif>
                    <div class="form-text">Một ảnh dùng chung cho tất cả tuyến tạo nhanh.</div>
                    @error('vehicle_photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <div id="quick-vehicle-photo-preview" class="@if(! $quickHasPhoto) d-none @endif">
                        <img src="@if($quickHasPhoto){{ $quickVehiclePhotos[$quickSeats] }}@endif"
                             alt=""
                             class="rounded border"
                             width="120"
                             height="84"
                             style="object-fit:cover"
                             id="quick-vehicle-photo-img">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="console-panel mb-4" id="trip-offer-list">
    <div class="console-panel-head">
        <div class="console-panel-head-accent d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
            <h2 class="mb-0">Chuyến đang hiển thị</h2>
            @if(! $activeOffers->isEmpty())
                <button type="submit"
                        form="trip-offer-bulk-delete"
                        id="trip-offer-bulk-delete-btn"
                        class="btn btn-outline-danger btn-sm"
                        disabled>
                    Xóa đã chọn
                </button>
            @endif
        </div>
    </div>
    <div class="console-panel-body flush">
        @error('template_ids')
            <div class="alert alert-danger mx-3 mt-3 mb-0">{{ $message }}</div>
        @enderror
        @if($activeOffers->isEmpty())
            <div class="console-empty py-4">
                <p class="mb-0 text-muted small">Chưa có chuyến nào đang hiển thị.</p>
            </div>
        @else
            <form id="trip-offer-bulk-delete"
                  method="POST"
                  action="{{ route('operator.tripOffers.bulkDestroy') }}"
                  data-confirm="Xóa các tuyến đã chọn khỏi trang đặt vé?"
                  data-confirm-title="Xóa tuyến"
                  data-confirm-variant="danger"
                  data-confirm-ok="Xóa">
                @csrf
                @method('DELETE')
            </form>
            <div class="console-table-wrap">
                <table class="console-table">
                    <thead>
                        <tr>
                            <th class="col-check" scope="col">
                                <input type="checkbox"
                                       class="form-check-input trip-offer-select-all"
                                       id="trip-offer-select-all"
                                       aria-label="Chọn tất cả trên trang này">
                            </th>
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
                            <td class="col-check">
                                <input type="checkbox"
                                       class="form-check-input trip-offer-select"
                                       name="template_ids[]"
                                       value="{{ $offer->id }}"
                                       form="trip-offer-bulk-delete"
                                       aria-label="Chọn {{ $offer->route->departure }} → {{ $offer->route->destination }}">
                            </td>
                            <td class="cell-primary">{{ $offer->route->departure }} → {{ $offer->route->destination }}</td>
                            <td class="cell-muted">{{ \App\Support\DepartureTimeDisplay::label($offer->departure_time) }}</td>
                            <td>{{ \App\Support\VehicleCapacityOptions::label($offer->vehicle->capacity) }}</td>
                            <td class="cell-muted">{{ $offer->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="console-table-actions">
                                    <a href="{{ $editUrl }}" class="btn btn-outline-primary btn-sm">Sửa</a>
                                    <form method="POST" action="{{ route('operator.tripOffers.destroy', $offer) }}"
                                          data-confirm="Xóa tuyến {{ $offer->route->departure }} → {{ $offer->route->destination }} ({{ $offer->departure_time ? substr((string) $offer->departure_time, 0, 5) : 'tự chọn giờ' }}, {{ $offer->vehicle->capacity }} chỗ)? Tuyến sẽ không còn hiển thị trên trang đặt vé."
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
                        'required' => false,
                    ])
                </div>
                <div class="col-md-6">
                    @include('partials.departure-time-input', [
                        'name' => 'expected_arrival_time',
                        'id' => 'offer-arrival-time',
                        'label' => 'Giờ dự kiến đến (tuỳ chọn)',
                        'value' => old('expected_arrival_time', $formData['expected_arrival_time'] ?? ''),
                        'required' => false,
                    ])
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="offer-distance-km">Quãng đường (km)</label>
                    <input type="number" name="distance_km" id="offer-distance-km" class="form-control"
                           min="1" max="2000" step="1"
                           value="{{ old('distance_km', $formData['distance_km'] ?? '') }}"
                           placeholder="Chọn điểm đi & điểm đến">
                    <div class="form-text" id="offer-rate-hint">Có thể sửa km — giá sẽ tự tính lại.</div>
                </div>
            </div>

            <div id="offer-price-preview" class="mb-3 d-none"></div>

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

@push('modals')
@include('partials.booking-qr-modal')
@endpush

@push('scripts')
<script src="{{ asset('js/booking-qr.js') }}"></script>
<script>
    window.tripOfferQuoteUrl = @json($quoteUrl ?? route('operator.tripOffers.quote'));
    window.quickTripVehiclePhotos = @json($quickVehiclePhotos ?? []);
</script>
<script src="{{ asset('js/trip-offer-pricing.js') }}"></script>
<script>
(function () {
    var seatsEl = document.getElementById('quick-seats');
    var photoInput = document.getElementById('quick-vehicle-photo');
    var savedPreview = document.getElementById('quick-vehicle-photo-preview');
    var savedImg = document.getElementById('quick-vehicle-photo-img');
    var photos = window.quickTripVehiclePhotos || {};
    var objectUrl = null;

    function revokeObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function savedPhotoUrl() {
        return seatsEl ? (photos[String(seatsEl.value)] || '') : '';
    }

    function showPreview(src) {
        if (!savedPreview || !savedImg) {
            return;
        }
        if (!src) {
            savedPreview.classList.add('d-none');
            savedImg.removeAttribute('src');
            return;
        }
        savedImg.src = src;
        savedPreview.classList.remove('d-none');
    }

    function syncQuickPhotoRequirement() {
        if (!seatsEl || !photoInput) {
            return;
        }
        var hasSaved = Boolean(photos[String(seatsEl.value)]);
        var hasFile = photoInput.files && photoInput.files.length > 0;
        photoInput.required = !hasSaved && !hasFile;
    }

    function refreshPreview() {
        revokeObjectUrl();
        var file = photoInput && photoInput.files && photoInput.files[0];
        if (file) {
            objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl);
        } else {
            showPreview(savedPhotoUrl() || null);
        }
        syncQuickPhotoRequirement();
    }

    if (seatsEl) {
        seatsEl.addEventListener('change', refreshPreview);
    }

    if (photoInput) {
        photoInput.addEventListener('change', refreshPreview);
    }

    refreshPreview();

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

    var quickForm = document.querySelector('#trip-offer-quick form');
    var quickDate = document.getElementById('quick-service-date');
    if (quickForm && quickDate) {
        function syncQuickConfirm() {
            var value = quickDate.value;
            if (!value) {
                return;
            }
            var parts = value.split('-');
            if (parts.length !== 3) {
                return;
            }
            var label = parts[2] + '/' + parts[1] + '/' + parts[0];
            quickForm.setAttribute('data-confirm', 'Tạo tất cả tuyến từ điểm đi đã chọn cho ngày ' + label + '?');
        }
        quickDate.addEventListener('change', syncQuickConfirm);
        syncQuickConfirm();
    }

    var bulkForm = document.getElementById('trip-offer-bulk-delete');
    var bulkBtn = document.getElementById('trip-offer-bulk-delete-btn');
    var selectAll = document.getElementById('trip-offer-select-all');
    var rowChecks = document.querySelectorAll('.trip-offer-select');

    function selectedOfferCount() {
        var count = 0;
        rowChecks.forEach(function (el) {
            if (el.checked) {
                count++;
            }
        });
        return count;
    }

    function syncBulkDeleteUi() {
        var count = selectedOfferCount();
        if (bulkBtn) {
            bulkBtn.disabled = count === 0;
            bulkBtn.textContent = count > 0 ? ('Xóa đã chọn (' + count + ')') : 'Xóa đã chọn';
        }
        if (bulkForm) {
            bulkForm.setAttribute(
                'data-confirm',
                count > 0
                    ? ('Xóa ' + count + ' tuyến đã chọn khỏi trang đặt vé?')
                    : 'Xóa các tuyến đã chọn khỏi trang đặt vé?',
            );
        }
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < rowChecks.length;
            selectAll.checked = rowChecks.length > 0 && count === rowChecks.length;
        }
    }

    rowChecks.forEach(function (el) {
        el.addEventListener('change', syncBulkDeleteUi);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (el) {
                el.checked = selectAll.checked;
            });
            syncBulkDeleteUi();
        });
    }

    syncBulkDeleteUi();
})();
</script>
@endpush
