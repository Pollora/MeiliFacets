<div class="meilifacetsFacets" data-apply="{{ $applyMode }}" {{ $hook('facets') }} {{ $scrollMark() }}>
    @foreach ($listing->remainingFacets() as $filter)
        <x-dynamic-component :component="$componentFor($filter)" :facet="$filter" :name="$name"
                             :collapsible="$collapsible" />
    @endforeach
    @if ($needsApplyButton)
        <button type="button" class="meilifacetsFacetsApply" {{ $hook('apply') }}>
            {{ __('Apply filters') }}
        </button>
    @endif
</div>
