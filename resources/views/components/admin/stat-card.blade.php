@props(['label', 'value', 'accent' => false])

<div class="stat-card {{ $accent ? 'stat-card-accent' : '' }}">
    <div class="stat-card-label">{{ $label }}</div>
    <div class="stat-card-value">{{ $value }}</div>
</div>
