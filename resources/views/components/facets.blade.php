@php($resolved = $listing())
<div class="meilifacetsFacets" data-apply="{{ $resolved->applyMode()->value }}" {{ $hook('facets') }}>
    @foreach ($resolved->facets() as $facet)
        @php($values = $resolved->valuesOf($facet))
        <fieldset class="meilifacetsFacet" data-taxonomy="{{ $facet->taxonomy }}" @if ($values === []) hidden @endif>
            <legend class="meilifacetsFacetLabel">{{ $facet->label }}</legend>
            <ul class="meilifacetsFacetValues">
                @foreach ($values as $value)
                    <li class="meilifacetsFacetValue" @if ($value->folded) hidden @endif {{ $hook('facet-value') }}>
                        <label>
                            <input type="{{ $inputType($facet) }}"
                                   name="{{ $inputName($facet) }}"
                                   value="{{ $value->slug }}"
                                   aria-describedby="{{ $countId($facet, $value) }}"
                                   @checked($value->selected) {{ $hook('input') }}>
                            <span class="meilifacetsFacetName">{{ $value->label }}</span>
                            {{-- Described, not named: the count changes at every filtering,
                                 and a screen reader would rename the box under the cursor. --}}
                            <span class="meilifacetsFacetCount" id="{{ $countId($facet, $value) }}" {{ $hook('count') }}>
                                {{ $countLabel($value) }}
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endforeach
    @if ($resolved->applyMode()->needsButton())
        <button type="button" class="meilifacetsFacetsApply" {{ $hook('apply') }}>
            {{ __('Apply filters') }}
        </button>
    @endif
</div>
