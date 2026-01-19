@props(['type' => 'info'])

@php
$class = match($type) {
    'success' => 'badge-success',
    'warning' => 'badge-warning',
    'danger' => 'badge-danger',
    'info' => 'badge-info',
    default => 'badge-muted',
};
@endphp

<span class="badge {{ $class }}">{{ $slot }}</span>
