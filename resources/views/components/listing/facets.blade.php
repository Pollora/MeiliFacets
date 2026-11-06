@unless ($unwrapped)
<div class="meilifacetsFacets" {{ $hook('facets') }} {{ $scrollMark() }}>
@endunless
    @foreach ($remainingFilters() as $filter)
        <x-dynamic-component :component="$componentFor($filter)" :facet="$filter" :name="$name"
                             :collapsible="$collapsible" :scroll="$unwrapped && $scroll" />
    @endforeach
    @if ($needsApplyButton)
        <x-meilifacets::listing.apply :name="$name" shape="block" :scroll="$unwrapped && $scroll" />
    @endif
@unless ($unwrapped)
</div>
@endunless
