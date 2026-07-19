@php
    $iconKey = $iconKey ?? 'other';
@endphp
<svg class="be-vehicle-icon__svg" viewBox="0 0 48 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
@if($iconKey === 'suv')
    <path d="M8 20h32l-2.5-8.5A4 4 0 0 0 33.7 9H14.3a4 4 0 0 0-3.8 2.5L8 20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M12 20v2.5a2.5 2.5 0 0 0 5 0V20M31 20v2.5a2.5 2.5 0 0 0 5 0V20" stroke="currentColor" stroke-width="2"/>
    <path d="M16 13h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
@elseif($iconKey === 'limousine')
    <path d="M4 19h40l-3-7a3 3 0 0 0-2.8-1.8H9.8A3 3 0 0 0 7 12l-3 7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M10 19v2a2 2 0 0 0 4 0v-2M34 19v2a2 2 0 0 0 4 0v-2" stroke="currentColor" stroke-width="2"/>
    <path d="M14 12.5h20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
@elseif($iconKey === 'van')
    <path d="M6 20h36V12a3 3 0 0 0-3-3H14L6 14v6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M12 20v2a2 2 0 0 0 4 0v-2M32 20v2a2 2 0 0 0 4 0v-2" stroke="currentColor" stroke-width="2"/>
@else
    {{-- sedan / other --}}
    <path d="M9 20h30l-2.2-7A3.5 3.5 0 0 0 33.5 10H14.5a3.5 3.5 0 0 0-3.3 2.3L9 20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M13 20v2a2.2 2.2 0 0 0 4.4 0V20M30.6 20v2a2.2 2.2 0 0 0 4.4 0V20" stroke="currentColor" stroke-width="2"/>
    <path d="M16 13.5h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
@endif
</svg>
