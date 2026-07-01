@php
    $title = $title ?? 'Chưa có dữ liệu';
    $hint = $hint ?? null;
    $icon = $icon ?? 'inbox';
@endphp
<div class="driver-empty-state">
    <div class="driver-empty-state-icon" aria-hidden="true">
        @if($icon === 'search')
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        @elseif($icon === 'route')
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
        @elseif($icon === 'history')
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>
        @else
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16v11H4z"/><path d="M8 7V5h8v2"/></svg>
        @endif
    </div>
    <p class="driver-empty-state-title">{{ $title }}</p>
    @if($hint)
        <p class="driver-empty-state-hint">{{ $hint }}</p>
    @endif
</div>
