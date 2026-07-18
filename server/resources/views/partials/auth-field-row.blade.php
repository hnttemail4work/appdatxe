@php
    $fieldLabel = $fieldLabel ?? '';
    $fieldName = $fieldName ?? 'phone';
    $fieldId = $fieldId ?? $fieldName;
    $fieldType = $fieldType ?? 'tel';
    $fieldValue = $fieldValue ?? old($fieldName);
    $fieldPlaceholder = $fieldPlaceholder ?? '';
    $fieldAutocomplete = $fieldAutocomplete ?? 'off';
    $fieldInputmode = $fieldInputmode ?? null;
    $fieldRequired = $fieldRequired ?? true;
    $nextType = $nextType ?? 'button';
    $nextAttr = $nextAttr ?? 'data-auth-next';
    $nextAria = $nextAria ?? 'Tiếp tục';
    $footerHtml = $footerHtml ?? null;
@endphp
<div class="auth-field-block">
    @if($fieldLabel !== '')
        <label class="auth-field-label" for="{{ $fieldId }}">{{ $fieldLabel }}</label>
    @endif
    <div class="auth-field-row">
        <input
            type="{{ $fieldType }}"
            name="{{ $fieldName }}"
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            class="auth-field-input @error($fieldName) is-invalid @enderror"
            @if($fieldRequired) required @endif
            autocomplete="{{ $fieldAutocomplete }}"
            @if($fieldInputmode) inputmode="{{ $fieldInputmode }}" @endif
            @if($fieldPlaceholder !== '') placeholder="{{ $fieldPlaceholder }}" @endif
            autofocus
        >
        <button type="{{ $nextType }}" class="auth-next-btn"@if($nextAttr !== '') {{ $nextAttr }}@endif aria-label="{{ $nextAria }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </button>
    </div>
    <div class="auth-field-error" @error($fieldName)@else hidden @enderror>@error($fieldName){{ $message }}@enderror</div>
    @if($footerHtml)
        <div class="auth-field-footer">{!! $footerHtml !!}</div>
    @endif
</div>
