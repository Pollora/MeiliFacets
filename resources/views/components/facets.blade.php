@php($resolved = $listing())
<div class="meilifacetsFacets" data-apply="{{ $resolved->listing->applyMode()->value }}">
    @foreach ($resolved->facets() as $facet)
        @php($values = $resolved->valuesOf($facet))
        @continue ($values === [])
        <fieldset class="meilifacetsFacet" data-taxonomy="{{ $facet->taxonomy }}">
            <legend class="meilifacetsFacetLabel">{{ $facet->label }}</legend>
            <ul class="meilifacetsFacetValues">
                @foreach ($values as $value)
                    <li class="meilifacetsFacetValue" @if ($value->folded) hidden @endif>
                        <label>
                            <input type="{{ $inputType($facet) }}"
                                   name="{{ $inputName($facet) }}"
                                   value="{{ $value->slug }}"
                                   @checked($value->selected)>
                            <span class="meilifacetsFacetName">{{ $value->label }}</span>
                            <span class="meilifacetsFacetCount">{{ $value->count }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endforeach
    @if ($resolved->listing->applyMode()->needsButton())
        <button type="button" class="meilifacetsFacetsApply">
            {{ __('Appliquer les filtres', 'meilifacets') }}
        </button>
    @endif
</div>
