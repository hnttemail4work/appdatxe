@php
/** @var string $name */
/** @var string $id */
/** @var string|null $value */
/** @var bool $required */
$name = $name ?? 'pickup_time';
$id = $id ?? 'modal-pickup-time';
$value = $value ?? '06:00 SA';
$required = $required ?? true;
$label = $label ?? 'Giờ đón';
$hint = $hint ?? 'Không bắt buộc. Nếu đặt trong ngày, chọn sau ít nhất 30 phút so với hiện tại.';
@endphp
<label class="form-label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
<input type="text" name="{{ $name }}" id="{{ $id }}"
       class="form-control vi-pickup-time @error($name) is-invalid @enderror"
       @if($required) required @endif
       autocomplete="off"
       inputmode="numeric"
       placeholder="06:00 SA"
       data-validate-label="{{ $label }}"
       value="{{ $value }}">
<div class="form-text">{{ $hint }}</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
