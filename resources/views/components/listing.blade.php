@php($resolved = $listing())
<div {{ $attributes->class('meilifacets') }} data-listing="{{ $name }}">
    @if ($resolved->failed())
        <x-meilifacets::unavailable />
    @else
        {{ $slot }}
    @endif
</div>
