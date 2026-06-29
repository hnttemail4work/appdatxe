@php
/** @var string $variant */
$variant = $variant ?? 'neutral';
@endphp
<span class="status-pill status-pill--{{ $variant }}">{{ $slot ?? '' }}</span>
