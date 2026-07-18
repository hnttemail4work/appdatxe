@php
    /** @var string $activeKey */
    /** @var array<int, array{key: string, label: string, short?: string, badge?: int|string, hot?: bool}> $tabs */
    $activeKey = $activeKey ?? ($tabs[0]['key'] ?? '');
@endphp
<nav class="driver-app-dock" style="--driver-dock-tabs: {{ count($tabs) }};" aria-label="Menu tài xế">
    @foreach($tabs as $tab)
        @php
            $isActive = ($tab['key'] ?? '') === $activeKey;
            $badge = $tab['badge'] ?? null;
            $showBadge = $badge !== null && $badge !== '' && (int) $badge > 0;
            $tabKey = $tab['key'] ?? '';
            $short = $tab['short'] ?? ($tab['label'] ?? '');
        @endphp
        <button type="button"
                class="driver-dock-item {{ $isActive ? 'is-active' : '' }}"
                data-driver-tab="{{ $tabKey }}"
                aria-label="{{ $tab['label'] }}"
                title="{{ $tab['label'] }}"
                @if($isActive) aria-current="page" @endif>
            <span class="driver-dock-icon" aria-hidden="true">
                @if($tabKey === 'trips')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                @elseif($tabKey === 'history')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>
                @elseif($tabKey === 'earnings' || $tabKey === 'deposit')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M14.5 8.5c-.6-1-1.5-1.5-2.5-1.5-1.7 0-3 1.1-3 2.5s1.3 2.5 3 2.5 3 1.1 3 2.5-1.3 2.5-3 2.5c-1 0-1.9-.5-2.5-1.5"/><path d="M12 5.5v13"/></svg>
                @elseif($tabKey === 'inbox')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                @elseif($tabKey === 'account')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/></svg>
                @else
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg>
                @endif
            </span>
            <span class="driver-dock-label">{{ $short }}</span>
            @if($showBadge)
                <span class="driver-dock-badge {{ ! empty($tab['hot']) ? 'is-hot' : '' }}">{{ $badge }}</span>
            @endif
        </button>
    @endforeach
</nav>
