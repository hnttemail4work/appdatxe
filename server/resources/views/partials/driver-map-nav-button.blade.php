@php
/** @var array{label: string, url: string}|null $mapNav */
$mapNav = $mapNav ?? null;
$compact = $compact ?? false;
@endphp
@if(! empty($mapNav['url']) || ! empty($mapNav['google_url']))
    <a href="{{ $mapNav['google_url'] ?? $mapNav['url'] }}"
       class="driver-map-nav-btn {{ $compact ? 'driver-map-nav-btn--compact' : '' }}"
       data-driver-map-nav
       data-google-url="{{ $mapNav['google_url'] ?? '' }}"
       data-geo-url="{{ $mapNav['url'] ?? '' }}"
       @if(! empty($mapNav['dest_lat'])) data-dest-lat="{{ $mapNav['dest_lat'] }}" @endif
       @if(! empty($mapNav['dest_lng'])) data-dest-lng="{{ $mapNav['dest_lng'] }}" @endif
       @if(! empty($mapNav['use_current_origin'])) data-map-nav-use-current-origin="1" @endif
       aria-label="{{ $mapNav['label'] }}">
        <span class="driver-map-nav-btn__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
        </span>
        <span class="driver-map-nav-btn__label">{{ $mapNav['label'] }}</span>
    </a>
@endif
