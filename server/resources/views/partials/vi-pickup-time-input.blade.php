@php
/** @var string $name */
/** @var string $id */
/** @var string|null $value */
/** @var bool $required */
$name = $name ?? 'pickup_time';
$id = $id ?? 'modal-pickup-time';
$value = $value ?? '';
$required = $required ?? true;
$label = $label ?? 'Giờ đón';
@endphp
<div class="vi-pickup-time-widget" data-vi-pickup-time-root>
    <label class="form-label" for="{{ $id }}-hour">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
    <div class="vi-pickup-time-row">
        <select id="{{ $id }}-hour" class="form-select vi-pickup-hour" data-vi-hour aria-label="Giờ"></select>
        <span class="vi-pickup-colon" aria-hidden="true">:</span>
        <select id="{{ $id }}-minute" class="form-select vi-pickup-minute" data-vi-minute aria-label="Phút">
            @for($m = 0; $m < 60; $m += 5)
                <option value="{{ $m }}">{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
            @endfor
        </select>
        <div class="vi-pickup-period" role="group" aria-label="Buổi trong ngày">
            <button type="button" class="vi-pickup-period-btn" data-vi-period="dem">Đêm</button>
            <button type="button" class="vi-pickup-period-btn is-active" data-vi-period="sang">Sáng</button>
            <button type="button" class="vi-pickup-period-btn" data-vi-period="chieu">Chiều</button>
            <button type="button" class="vi-pickup-period-btn" data-vi-period="toi">Tối</button>
        </div>
    </div>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}"
           class="vi-pickup-time @error($name) is-invalid @enderror"
           @if($required) required data-vi-required="1" @endif
           data-validate-label="{{ $label }}"
           value="{{ $value }}">
</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
