<div {{ $attributes->class('meilifacets') }} data-listing="{{ $listing->name() }}" {{ $contract }}>
    @if ($listing->failed())
        <x-meilifacets::unavailable />
    @else
        {{ $slot }}
    @endif
</div>
