@php
/** @var array{label: string, url: string}|null $mapNav */
$mapNav = $mapNav ?? null;
$compact = $compact ?? false;
@endphp
@if(! empty($mapNav['url']))
    <a href="{{ $mapNav['url'] }}"
       class="driver-map-nav-btn {{ $compact ? 'driver-map-nav-btn--compact' : '' }}"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="{{ $mapNav['label'] }}">
        <span class="driver-map-nav-btn__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
        </span>
        <span class="driver-map-nav-btn__label">{{ $mapNav['label'] }}</span>
    </a>
@endif
