@php
/** @var array<string, mixed> $vehicle */
$vehicle = $vehicle ?? [];
$seats = old('seats', $vehicle['seats'] ?? '');
$photoRequired = empty($vehicle['photo_url']);
@endphp
<div class="row g-3">
    <div class="col-md-4 col-lg-3">
        <label class="form-label" for="offer-seats">Số chỗ <span class="text-danger">*</span></label>
        <select name="seats" id="offer-seats" class="form-select form-select-sm @error('seats') is-invalid @enderror" required
                data-validate-label="Số chỗ">
            @include('partials.vehicle-capacity-options', ['selected' => $seats])
        </select>
        @error('seats')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 col-lg-3">
        <label class="form-label small" for="offer-whole-car-one-way">Cả xe — một chiều (đ) <span class="text-danger">*</span></label>
        <input type="number" name="whole_car_one_way" id="offer-whole-car-one-way"
               class="form-control form-control-sm @error('whole_car_one_way') is-invalid @enderror"
               min="10000" step="1000" required data-validate-label="Giá cả xe một chiều"
               value="{{ old('whole_car_one_way', $vehicle['whole_car_one_way'] ?? '') }}">
        @error('whole_car_one_way')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 col-lg-3">
        <label class="form-label small" for="offer-seat-one-way">Ghép xe — một chiều / ghế (đ) <span class="text-danger">*</span></label>
        <input type="number" name="seat_one_way" id="offer-seat-one-way"
               class="form-control form-control-sm @error('seat_one_way') is-invalid @enderror"
               min="10000" step="1000" required data-validate-label="Giá ghép xe một chiều"
               value="{{ old('seat_one_way', $vehicle['seat_one_way'] ?? '') }}">
        @error('seat_one_way')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 col-lg-3">
        <label class="form-label small" for="offer-whole-car-round">Cả xe — khứ hồi (đ) <span class="text-danger">*</span></label>
        <input type="number" name="whole_car_round" id="offer-whole-car-round"
               class="form-control form-control-sm @error('whole_car_round') is-invalid @enderror"
               min="10000" step="1000" required data-validate-label="Giá cả xe khứ hồi"
               value="{{ old('whole_car_round', $vehicle['whole_car_round'] ?? '') }}">
        @error('whole_car_round')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 col-lg-3">
        <label class="form-label small" for="offer-seat-round">Ghép xe — khứ hồi / ghế (đ) <span class="text-danger">*</span></label>
        <input type="number" name="seat_round" id="offer-seat-round"
               class="form-control form-control-sm @error('seat_round') is-invalid @enderror"
               min="10000" step="1000" required data-validate-label="Giá ghép xe khứ hồi"
               value="{{ old('seat_round', $vehicle['seat_round'] ?? '') }}">
        @error('seat_round')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        @if(! empty($vehicle['photo_url']))
            <div class="mb-2">
                <img src="{{ $vehicle['photo_url'] }}" alt="" class="rounded border" width="80" height="56" style="object-fit:cover">
                <div class="small text-muted mt-1">Ảnh hiện tại — tải ảnh mới nếu muốn thay.</div>
            </div>
        @endif
        <label class="form-label small" for="offer-vehicle-photo">
            Ảnh xe @if($photoRequired)<span class="text-danger">*</span>@endif
        </label>
        <input type="file" name="vehicle_photo" id="offer-vehicle-photo"
               class="form-control form-control-sm @error('vehicle_photo') is-invalid @enderror"
               accept="image/jpeg,image/png,image/webp,image/*"
               @if($photoRequired) required @endif
               data-validate-label="Ảnh xe">
        @error('vehicle_photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
