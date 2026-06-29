@php
    /** @var string $prefix */
    /** @var array<int, array{key: string, label: string, badge?: int|string, hot?: bool}> $tabs */
    /** @var string|null $activeKey */
    $activeKey = $activeKey ?? ($tabs[0]['key'] ?? '');
@endphp
<div class="screen-tabs-wrap" data-tab-prefix="{{ $prefix }}">
    <ul class="nav nav-tabs screen-tabs" id="{{ $prefix }}-nav" role="tablist">
        @foreach($tabs as $tab)
            @php $isActive = ($tab['key'] ?? '') === $activeKey; @endphp
            <li class="nav-item" role="presentation">
                <button type="button"
                    class="nav-link {{ $isActive ? 'active' : '' }}"
                    id="{{ $prefix }}-tab-{{ $tab['key'] }}"
                    data-bs-toggle="tab"
                    data-bs-target="#{{ $prefix }}-pane-{{ $tab['key'] }}"
                    role="tab"
                    aria-controls="{{ $prefix }}-pane-{{ $tab['key'] }}"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}">
                    {{ $tab['label'] }}
                    @if(isset($tab['badge']) && $tab['badge'] !== '' && $tab['badge'] !== 0)
                        <span class="status-pill status-pill--{{ ! empty($tab['hot']) ? 'accent' : 'neutral' }} ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content screen-tab-panels" id="{{ $prefix }}-content">
