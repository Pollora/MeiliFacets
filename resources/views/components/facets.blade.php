<div class="meilifacetsFacets" data-apply="{{ $applyMode }}" {{ $hook('facets') }} {{ $scrollMark() }}>
    @foreach ($listing->remainingFacets() as $facet)
        <x-meilifacets::facet :facet="$facet" :name="$name" />
    @endforeach
    @if ($needsApplyButton)
        <button type="button" class="meilifacetsFacetsApply" {{ $hook('apply') }}>
            {{ __('Apply filters') }}
        </button>
    @endif
</div>
