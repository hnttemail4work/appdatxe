{{-- @param string $icon @param string $value @param string $label @param string $tone primary|success|warning|info|danger --}}
<div class="console-stat">
    <div class="console-stat-icon {{ $tone ?? 'primary' }}">{{ $icon }}</div>
    <div>
        <div class="console-stat-value">{{ $value }}</div>
        <div class="console-stat-label">{{ $label }}</div>
    </div>
</div>
