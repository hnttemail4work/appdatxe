@php
use App\Support\DepartureTimeDisplay;

/** @var string $name */
/** @var string $id */
/** @var string|null $value */
/** @var bool $required */
$name = $name ?? 'pickup_time';
$id = $id ?? 'modal-pickup-time';
$value = $value ?? '';
$required = $required ?? true;
$label = $label ?? 'Giờ đón';
$displayValue = $value !== '' && $value !== null
    ? DepartureTimeDisplay::normalizeForClock($value)
    : '';
@endphp
<label class="form-label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
<input type="time" name="{{ $name }}" id="{{ $id }}"
       class="form-control @error($name) is-invalid @enderror"
       @if($required) required @endif
       data-validate-label="{{ $label }}"
       value="{{ old($name, $displayValue) }}">
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
