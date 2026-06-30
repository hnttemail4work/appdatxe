@php
/** @var string $name */
/** @var string $id */
/** @var string|null $value */
/** @var bool $required */
$name = $name ?? 'departure_time';
$id = $id ?? 'departure-time';
$value = $value ?? '';
$required = $required ?? true;
$label = $label ?? 'Giờ khởi hành';
$hint = $hint ?? 'Để trống nếu khách tự chọn giờ đón trên trang đặt vé.';
@endphp
<label class="form-label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
<input type="time" name="{{ $name }}" id="{{ $id }}"
       class="form-control @error($name) is-invalid @enderror"
       @if($required) required @endif
       data-validate-label="{{ $label }}"
       value="{{ $value }}">
@if(($name ?? 'departure_time') === 'departure_time')
<div class="form-text">{{ $hint }}</div>
@endif
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
