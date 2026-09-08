<div class="meilifacetsFacets" data-apply="{{ $applyMode }}" {{ $hook('facets') }} {{ $scrollMark() }}>
    @foreach ($listing->facets() as $facet)
        @php($values = $listing->valuesOf($facet))
        <fieldset class="meilifacetsFacet" data-taxonomy="{{ $facet->taxonomy }}" @if ($values === []) hidden @endif>
            <legend class="meilifacetsFacetLabel">{{ $facet->label }}</legend>
            <ul class="meilifacetsFacetValues">
                @foreach ($values as $value)
                    @php($countId = $ids->facetCount($facet->taxonomy, $value->slug))
                    <li class="meilifacetsFacetValue" @if ($value->folded) hidden @endif {{ $hook('facet-value') }}>
                        <label>
                            <input type="{{ $inputType($facet) }}"
                                   name="{{ $listing->parameterFor($facet->taxonomy) }}"
                                   value="{{ $value->slug }}"
                                   aria-describedby="{{ $countId }}"
                                   @checked($value->selected) {{ $hook('input') }}>
                            <span class="meilifacetsFacetName">{{ $value->label }}</span>
                            {{-- Described, not named: the count changes at every filtering,
                                 and a screen reader would rename the box under the cursor. --}}
                            <span class="meilifacetsFacetCount" id="{{ $countId }}" {{ $hook('count') }}>
                                {{ $countLabel($value) }}
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endforeach
    @if ($needsApplyButton)
        <button type="button" class="meilifacetsFacetsApply" {{ $hook('apply') }}>
            {{ __('Apply filters') }}
        </button>
    @endif
</div>
