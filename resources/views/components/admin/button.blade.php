@props(['type' => 'primary', 'size' => 'default', 'href' => null])

@php
$classes = 'btn btn-' . $type;
if ($size === 'sm') {
    $classes .= ' btn-sm';
}
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
