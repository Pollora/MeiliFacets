<fieldset {{ $attributes->class('meilifacetsFacet') }} data-taxonomy="{{ $facet->taxonomy }}"
          @if ($values === []) hidden @endif {{ $hook('facet') }} {{ $scrollMark() }}>
    <legend class="meilifacetsFacetLabel">{{ $facet->label }}</legend>
    <div class="meilifacetsFacetPanel" id="{{ $ids->facetPanel($facet->name) }}">
        <div class="meilifacetsFacetPanelInner">
            <ul class="meilifacetsFacetValues">
                @foreach ($values as $value)
                    @php($countId = $ids->facetCount($facet->name, $value->slug))
                    <li class="meilifacetsFacetValue" @if ($value->folded) hidden @endif {{ $hook('facet-value') }}>
                        <label>
                            <input type="{{ $inputType() }}"
                                   name="{{ $listing->parameterFor($facet->taxonomy) }}"
                                   value="{{ $value->slug }}"
                                   aria-describedby="{{ $countId }}"
                                   @checked($value->selected) {{ $hook('input') }}>
                            <span class="meilifacetsFacetName">{{ $value->label }}</span>
                            <span class="meilifacetsFacetCount" id="{{ $countId }}" {{ $hook('count') }}>
                                {{ $countLabel($value) }}
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
            <button type="button" class="meilifacetsFacetMore" aria-expanded="false"
                    @unless ($hasFoldedValues()) hidden @endunless {{ $hook('more') }}>
                {{ __('Show more') }}
            </button>
        </div>
    </div>
</fieldset>
