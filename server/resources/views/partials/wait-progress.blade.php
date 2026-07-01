@php
/** @var array<string, mixed>|null $waitProgress */
/** @var string $variant */
$waitProgress = $waitProgress ?? null;
$variant = $variant ?? 'guest';
$prefix = $variant === 'driver' ? 'driver-wait' : 'guest-trip-wait';
@endphp
@if($waitProgress)
<div class="{{ $prefix }} {{ $prefix }}--{{ $waitProgress['kind'] ?? 'default' }}"
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
