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
        @endphp
        <button type="button"
                class="driver-dock-item {{ $isActive ? 'is-active' : '' }}"
                data-driver-tab="{{ $tabKey }}"
                aria-label="{{ $tab['label'] }}"
                @if($isActive) aria-current="page" @endif>
            <span class="driver-dock-icon" aria-hidden="true">
                @if($tabKey === 'trips')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
                @elseif($tabKey === 'history')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg>
                @endif
            </span>
            <span class="driver-dock-label">{{ $tab['short'] ?? $tab['label'] }}</span>
            @if($showBadge)
                <span class="driver-dock-badge {{ ! empty($tab['hot']) ? 'is-hot' : '' }}">{{ $badge }}</span>
            @endif
        </button>
    @endforeach
</nav>
