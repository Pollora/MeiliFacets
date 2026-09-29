<div {{ $attributes->class('meilifacets') }} data-listing="{{ $listing->name() }}" {{ $contract }}>
    @if ($listing->failed())
        <x-meilifacets::listing.unavailable />
    @else
        {{ $slot }}
    @endif
</div>
