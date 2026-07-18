@php
    $nextType = $nextType ?? 'button';
    $nextAttr = $nextAttr ?? 'data-auth-next';
    $nextAria = $nextAria ?? 'Tiếp tục';
    $nextClass = $nextClass ?? '';
@endphp
<button type="{{ $nextType }}" class="auth-next-btn {{ $nextClass }}"@if(($nextAttr ?? '') !== '') {{ $nextAttr }}@endif aria-label="{{ $nextAria }}">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M5 12h14M13 6l6 6-6 6"/>
    </svg>
</button>
