@php
    $selected = isset($selected) ? (int) $selected : (int) old('seats', 0);
@endphp
<option value="">— Chọn số chỗ —</option>
@foreach(\App\Support\VehicleCapacityOptions::choices() as $capacity => $label)
    <option value="{{ $capacity }}" @selected($selected === $capacity)>{{ $label }}</option>
@endforeach
