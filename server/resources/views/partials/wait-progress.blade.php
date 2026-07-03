@php
/** @var array<string, mixed>|null $waitProgress */
/** @var string $variant */
/** @var string $layout */
$waitProgress = $waitProgress ?? null;
$variant = $variant ?? 'guest';
$layout = $layout ?? 'default';
$prefix = $variant === 'driver' ? 'driver-wait' : 'guest-trip-wait';
$kind = $waitProgress['kind'] ?? 'default';
@endphp
@if($waitProgress)
@if($layout === 'embedded' && $variant === 'driver')
<div class="{{ $prefix }} {{ $prefix }}--{{ $kind }} {{ $prefix }}--embedded"
     data-wait-progress
     data-wait-state="{{ json_encode($waitProgress, JSON_UNESCAPED_UNICODE) }}"
     role="status"
     aria-live="polite">
    <div class="{{ $prefix }}-embedded-row">
        @if(($waitProgress['kind'] ?? '') !== 'trip_accept')
            <div class="{{ $prefix }}-timer" data-field="wait_time" aria-hidden="true"></div>
        @endif
        <div class="{{ $prefix }}-copy">
            <div class="{{ $prefix }}-label" data-field="wait_label">{{ $waitProgress['label'] ?? '' }}</div>
            @if(! empty($waitProgress['hint']))
                <p class="{{ $prefix }}-hint mb-0" data-field="wait_hint">{{ $waitProgress['hint'] }}</p>
            @endif
        </div>
    </div>
    <div class="{{ $prefix }}-bar" aria-hidden="true">
        <div class="{{ $prefix }}-bar-fill" data-field="wait_bar"></div>
    </div>
</div>
@else
<div class="{{ $prefix }} {{ $prefix }}--{{ $kind }}"
     data-wait-progress
     data-wait-state="{{ json_encode($waitProgress, JSON_UNESCAPED_UNICODE) }}"
     role="status"
     aria-live="polite">
    <div class="{{ $prefix }}-head">
        <div class="{{ $prefix }}-copy">
            <div class="{{ $prefix }}-label" data-field="wait_label">{{ $waitProgress['label'] ?? '' }}</div>
            <div class="{{ $prefix }}-time" data-field="wait_time"></div>
        </div>
    </div>
    <div class="{{ $prefix }}-bar" aria-hidden="true">
        <div class="{{ $prefix }}-bar-fill" data-field="wait_bar"></div>
    </div>
    @if(! empty($waitProgress['hint']))
        <p class="{{ $prefix }}-hint mb-0" data-field="wait_hint">{{ $waitProgress['hint'] }}</p>
    @endif
</div>
@endif
@endif
